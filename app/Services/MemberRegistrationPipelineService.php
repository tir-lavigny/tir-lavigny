<?php

namespace App\Services;

use App\Mail\MemberRegistrationCompleted as MemberRegistrationCompletedMail;
use App\Mail\MemberRegistrationError;
use App\Mail\MemberRegistrationValidationRequested;
use App\MemberRegistrationStatus;
use App\MemberStatus;
use App\Models\Member;
use App\Models\MemberRegistration;
use App\Models\Product;
use App\Models\Season;
use App\Models\User;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MemberRegistrationPipelineService
{
    public function __construct(
        private readonly MemberSyncService $sync,
        private readonly ?MemberDavSyncFailureNotifier $davFailureNotifier = null,
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
            MemberRegistrationStatus::FORM_FILLED => $this->sendValidationRequest($registration),
            MemberRegistrationStatus::ERROR => $this->processFormSubmission($registration),
            MemberRegistrationStatus::MEMBER_CREATED => $this->retryAfterMemberCreated($registration),
            MemberRegistrationStatus::COMPLETED => $this->retryDavSync($registration),
            default => throw new \InvalidArgumentException('Aucune étape à réessayer.'),
        };
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

            if ($member->dav_uid && $registration->dav_uid !== $member->dav_uid) {
                $registration->dav_uid = $member->dav_uid;
                $registration->save();
            }

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

        if ($member->dav_uid && $registration->dav_uid !== $member->dav_uid) {
            $registration->dav_uid = $member->dav_uid;
            $registration->save();
        }

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

        return Member::create([
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
        ]);
    }

    private function generateBaseVouchers(Member $member): void
    {
        if ($member->status !== MemberStatus::ACTIVE) {
            Log::info('Base vouchers skipped for non-active member', [
                'member_id' => $member->id,
                'status' => $member->status->value,
            ]);

            return;
        }

        $season = Season::where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();

        if (! $season) {
            Log::warning('Base vouchers skipped because no active season exists', [
                'member_id' => $member->id,
            ]);

            return;
        }

        $basePackage = [
            'Tir du jeudi' => 3,
            'Ouverture' => 1,
            'Clôture' => 1,
        ];

        $created = 0;

        foreach ($basePackage as $productName => $count) {
            $product = Product::whereRaw('LOWER(name) = ?', [strtolower($productName)])->first();

            if (! $product) {
                Log::warning('Base voucher product not found', [
                    'member_id' => $member->id,
                    'season_id' => $season->id,
                    'product_name' => $productName,
                ]);

                continue;
            }

            $existingCount = Voucher::where('member_id', $member->id)
                ->where('season_id', $season->id)
                ->where('product_id', $product->id)
                ->withTrashed()
                ->count();

            $missing = $count - $existingCount;

            for ($i = 0; $i < $missing; $i++) {
                Voucher::create([
                    'discount_percent' => 100.00,
                    'discount_fixed' => null,
                    'product_id' => $product->id,
                    'season_id' => $season->id,
                    'member_id' => $member->id,
                ]);

                $created++;
            }
        }

        Log::info('Base vouchers generated for member', [
            'member_id' => $member->id,
            'season_id' => $season->id,
            'count' => $created,
        ]);
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
            'dav_uid' => $registration->dav_uid,
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
