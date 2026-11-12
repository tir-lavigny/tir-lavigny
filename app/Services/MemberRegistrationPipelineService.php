<?php

namespace App\Services;

use App\Mail\MemberRegistrationCompleted as MemberRegistrationCompletedMail;
use App\Mail\MemberRegistrationError;
use App\Mail\MemberRegistrationValidationRequested;
use App\MemberRegistrationStatus;
use App\Models\Member;
use App\Models\MemberRegistration;
use App\Models\Product;
use App\Models\Season;
use App\Models\User;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MemberRegistrationPipelineService
{
    public function __construct(
        private readonly DavServiceInterface $dav,
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
        $step = 'dav_contact';

        try {
            $davResult = $this->dav->createContactFromMemberRegistration($registration);
            $registration->dav_uid = $davResult['uid'];
            $this->transitionStatus($registration, MemberRegistrationStatus::DAV_CREATED);

            Log::info('Member registration DAV contact created', [
                'registration_id' => $registration->id,
                'dav_uid' => $davResult['uid'],
            ]);

            $step = 'member_creation';
            $member = $this->createMemberFromRegistration($registration);
            $registration->member()->associate($member);
            $this->transitionStatus($registration, MemberRegistrationStatus::MEMBER_CREATED);

            Log::info('Member registration member created', [
                'registration_id' => $registration->id,
                'member_id' => $member->id,
            ]);

            $step = 'voucher_generation';
            $this->generateBaseVouchers($member);

            $step = 'completion_email';
            $this->sendCompletionEmail($registration);
            $this->transitionStatus($registration, MemberRegistrationStatus::COMPLETED);

            Log::info('Member registration completed', [
                'registration_id' => $registration->id,
                'member_id' => $member->id,
            ]);
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
            MemberRegistrationStatus::DAV_CREATED => $this->processFromDavCreated($registration),
            MemberRegistrationStatus::MEMBER_CREATED => $this->processFromMemberCreated($registration),
            default => throw new \InvalidArgumentException('Aucune étape à réessayer.'),
        };
    }

    private function processFromDavCreated(MemberRegistration $registration): void
    {
        $step = 'member_creation';

        try {
            $member = $this->createMemberFromRegistration($registration);
            $registration->member()->associate($member);
            $this->transitionStatus($registration, MemberRegistrationStatus::MEMBER_CREATED);

            Log::info('Member registration member created', [
                'registration_id' => $registration->id,
                'member_id' => $member->id,
            ]);

            $step = 'voucher_generation';
            $this->generateBaseVouchers($member);

            $step = 'completion_email';
            $this->sendCompletionEmail($registration);
            $this->transitionStatus($registration, MemberRegistrationStatus::COMPLETED);

            Log::info('Member registration completed', [
                'registration_id' => $registration->id,
                'member_id' => $member->id,
            ]);
        } catch (\Throwable $e) {
            $this->handleFailure($registration, $e, $step);
        }
    }

    private function processFromMemberCreated(MemberRegistration $registration): void
    {
        try {
            $this->sendCompletionEmail($registration);
            $this->transitionStatus($registration, MemberRegistrationStatus::COMPLETED);

            Log::info('Member registration completed', [
                'registration_id' => $registration->id,
                'member_id' => $registration->member_id,
            ]);
        } catch (\Throwable $e) {
            $this->handleFailure($registration, $e, 'completion_email');
        }
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
            'dav_uid' => $registration->dav_uid,
        ]);
    }

    private function generateBaseVouchers(Member $member): void
    {
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
