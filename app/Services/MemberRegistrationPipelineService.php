<?php

namespace App\Services;

use App\Actions\Member\OnboardMemberAction;
use App\Mail\MemberRegistrationCompleted as MemberRegistrationCompletedMail;
use App\Mail\MemberRegistrationError;
use App\Mail\MemberRegistrationValidationRequested;
use App\MemberRegistrationStatus;
use App\MemberStatus;
use App\Models\Member;
use App\Models\MemberRegistration;
use App\Models\User;
use App\Rules\UniqueMemberAvs;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MemberRegistrationPipelineService
{
    public function __construct(
        private readonly MemberSyncService $sync,
        private readonly ?MemberDavSyncFailureNotifier $davFailureNotifier = null,
        private readonly ?SeasonVoucherService $seasonVoucherService = null,
        private readonly ?OnboardMemberAction $onboard = null,
        private readonly ?MemberRegistrationEmailVerificationService $emailVerification = null,
    ) {}

    /**
     * Notify admins that a form was filled and needs validation.
     */
    public function sendValidationRequest(MemberRegistration $registration): void
    {
        try {
            Mail::to(User::getMemberRegistrationEmails())->send(
                new MemberRegistrationValidationRequested($registration)
            );

            Log::info('Member registration validation email sent', [
                'registration_id' => $registration->id,
            ]);
        } catch (\Throwable $e) {
            $this->handleFailure($registration, $e, 'validation_email');
        }
    }

    /**
     * Run the full registration pipeline from FORM_FILLED status.
     */
    public function processFormSubmission(MemberRegistration $registration): void
    {
        $step = 'member_creation';

        try {
            $member = DB::transaction(function () use ($registration): Member {
                $member = $registration->member;

                if (! $member) {
                    $member = $this->createMemberFromRegistration($registration);
                    $registration->member()->associate($member);
                    $registration->save();
                    $this->transitionStatus($registration, MemberRegistrationStatus::MEMBER_CREATED);

                    Log::info('Member registration member created', [
                        'registration_id' => $registration->id,
                        'member_id' => $member->id,
                    ]);
                }

                return $member;
            });

            $this->processAfterMemberCreated($registration, $member);
        } catch (\Throwable $e) {
            $this->handleFailure($registration, $e, $step);
        }
    }

    /**
     * Retry the pipeline from the current status.
     */
    public function retryFromStep(MemberRegistration $registration): void
    {
        match ($registration->status) {
            MemberRegistrationStatus::FORM_FILLED => $this->retryFromFormFilled($registration),
            MemberRegistrationStatus::ERROR => $this->processFormSubmission($registration),
            MemberRegistrationStatus::MEMBER_CREATED => $this->retryAfterMemberCreated($registration),
            MemberRegistrationStatus::COMPLETED => $this->retryDavSync($registration),
            default => throw new \InvalidArgumentException('Aucune étape à réessayer.'),
        };
    }

    private function retryFromFormFilled(MemberRegistration $registration): void
    {
        if ($registration->isEmailVerified()) {
            $this->sendValidationRequest($registration);

            return;
        }

        ($this->emailVerification ?? app(MemberRegistrationEmailVerificationService::class))
            ->sendCode($registration);
    }

    private function retryAfterMemberCreated(MemberRegistration $registration): void
    {
        $member = $registration->member;

        if (! $member) {
            $this->processFormSubmission($registration);

            return;
        }

        try {
            $this->processAfterMemberCreated($registration, $member);
        } catch (\Throwable $e) {
            $this->handleFailure($registration, $e, 'voucher_generation');
        }
    }

    private function processAfterMemberCreated(MemberRegistration $registration, Member $member): void
    {
        $step = 'voucher_generation';

        try {
            DB::transaction(function () use ($registration, $member): void {
                $this->generateBaseVouchers($member);
                $this->transitionStatus($registration, MemberRegistrationStatus::COMPLETED);
            });

            $step = 'completion_email';
            $this->sendCompletionEmail($registration);

            Log::info('Member registration completed', [
                'registration_id' => $registration->id,
                'member_id' => $member->id,
            ]);

            $step = 'dav_sync';
            $this->sync->push($member);

            $this->notifyDavSyncFailure($registration, $member);
        } catch (\Throwable $e) {
            $this->handleFailure($registration, $e, $step);
        }
    }

    private function retryDavSync(MemberRegistration $registration): void
    {
        $member = $registration->member;

        if (! $member || ! $member->dav_sync_error) {
            throw new \InvalidArgumentException('Aucune synchronisation à réessayer.');
        }

        $this->sync->push($member);

        $this->notifyDavSyncFailure($registration, $member);
    }

    private function notifyDavSyncFailure(MemberRegistration $registration, Member $member): void
    {
        ($this->davFailureNotifier ?? app(MemberDavSyncFailureNotifier::class))
            ->notify($member, $registration);
    }

    private function transitionStatus(MemberRegistration $registration, MemberRegistrationStatus $status): void
    {
        $registration->status = $status;
        $registration->save();
    }

    private function createMemberFromRegistration(MemberRegistration $registration): Member
    {
        $formData = $registration->form_data;

        $this->assertAvsIsUnique($formData['avs'] ?? null);

        $onboard = $this->onboard ?? new OnboardMemberAction(
            $this->sync,
            $this->seasonVoucherService ?? app(SeasonVoucherService::class),
        );

        return $onboard->execute([
            'first_name' => $formData['first_name'],
            'last_name' => $formData['last_name'],
            'email' => $formData['email'] ?? null,
            'tel' => $formData['tel'] ?? null,
            'avs' => $formData['avs'] ?? null,
            'is_swiss_citizen' => $formData['is_swiss_citizen'] ?? null,
            'street' => $formData['street'] ?? null,
            'npa' => $formData['npa'] ?? null,
            'city' => $formData['city'] ?? null,
            'canton' => $formData['canton'] ?? null,
            'country' => $formData['country'] ?? null,
            'status' => MemberStatus::ACTIVE,
        ], generateVouchers: false, pushDav: false)['member'];
    }

    private function assertAvsIsUnique(mixed $avs): void
    {
        if (! is_string($avs) || trim($avs) === '') {
            return;
        }

        if (UniqueMemberAvs::exists($avs)) {
            throw new \RuntimeException('Ce numéro AVS est déjà utilisé par un autre membre.');
        }
    }

    private function generateBaseVouchers(Member $member): void
    {
        $voucherService = $this->seasonVoucherService ?? app(SeasonVoucherService::class);
        $result = $voucherService->generateBaseVouchersForMember($member);

        if ($result['missing'] !== []) {
            throw new \RuntimeException('Produits de base introuvables : '.implode(', ', $result['missing']).'.');
        }
    }

    private function sendCompletionEmail(MemberRegistration $registration): void
    {
        Mail::to(User::getMemberRegistrationEmails())->send(
            new MemberRegistrationCompletedMail($registration)
        );
    }

    private function handleFailure(MemberRegistration $registration, \Throwable $e, string $step): void
    {
        report($e);

        Log::error('Member registration pipeline failed', [
            'registration_id' => $registration->id,
            'status' => $registration->status->value,
            'step' => $step,
            'member_id' => $registration->member_id,
            'exception' => $e,
        ]);

        if ($step !== 'validation_email') {
            $registration->status = MemberRegistrationStatus::ERROR;
        }

        $registration->error_message = 'Erreur : '.$e->getMessage();
        $registration->failed_at = Carbon::now('Europe/Zurich')->utc();
        $registration->save();

        try {
            Mail::to(User::getMemberRegistrationEmails())->send(
                new MemberRegistrationError($registration)
            );
        } catch (\Throwable $emailException) {
            report($emailException);

            Log::error('Member registration error email failed', [
                'registration_id' => $registration->id,
                'exception' => $emailException,
            ]);
        }
    }
}
