<?php

namespace App\Services;

use App\Mail\MemberRegistrationCompleted as MemberRegistrationCompletedMail;
use App\Mail\MemberRegistrationError;
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
     * Run the full registration pipeline from FORM_FILLED status.
     */
    public function processFormSubmission(MemberRegistration $registration): void
    {
        try {
            // Step 1: Create DAV contact
            $davResult = $this->dav->createContactFromMemberRegistration($registration);
            $registration->dav_uid = $davResult['uid'];
            $this->transitionStatus($registration, MemberRegistrationStatus::DAV_CREATED);

            // Step 2: Create member + vouchers
            $member = $this->createMemberFromRegistration($registration);
            $registration->member()->associate($member);
            $this->transitionStatus($registration, MemberRegistrationStatus::MEMBER_CREATED);
            $this->generateBaseVouchers($member);

            // Step 3: Send completion email
            $this->sendCompletionEmail($registration);
            $this->transitionStatus($registration, MemberRegistrationStatus::COMPLETED);
        } catch (\Throwable $e) {
            $this->handleFailure($registration, $e);
        }
    }

    /**
     * Retry the pipeline from the current status.
     */
    public function retryFromStep(MemberRegistration $registration): void
    {
        match ($registration->status) {
            MemberRegistrationStatus::FORM_FILLED => $this->processFormSubmission($registration),
            MemberRegistrationStatus::DAV_CREATED => $this->processFromDavCreated($registration),
            MemberRegistrationStatus::MEMBER_CREATED => $this->processFromMemberCreated($registration),
            default => throw new \InvalidArgumentException('Aucune étape à réessayer.'),
        };
    }

    private function processFromDavCreated(MemberRegistration $registration): void
    {
        try {
            $member = $this->createMemberFromRegistration($registration);
            $registration->member()->associate($member);
            $this->transitionStatus($registration, MemberRegistrationStatus::MEMBER_CREATED);
            $this->generateBaseVouchers($member);

            $this->sendCompletionEmail($registration);
            $this->transitionStatus($registration, MemberRegistrationStatus::COMPLETED);
        } catch (\Throwable $e) {
            $this->handleFailure($registration, $e);
        }
    }

    private function processFromMemberCreated(MemberRegistration $registration): void
    {
        try {
            $this->sendCompletionEmail($registration);
            $this->transitionStatus($registration, MemberRegistrationStatus::COMPLETED);
        } catch (\Throwable $e) {
            $this->handleFailure($registration, $e);
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
            return;
        }

        $basePackage = [
            'Tir du jeudi' => 3,
            'Ouverture' => 1,
            'Clôture' => 1,
        ];

        foreach ($basePackage as $productName => $count) {
            $product = Product::whereRaw('LOWER(name) = ?', [strtolower($productName)])->first();

            if (! $product) {
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
            }
        }
    }

    private function sendCompletionEmail(MemberRegistration $registration): void
    {
        Mail::to(User::getMemberRegistrationEmails())->send(
            new MemberRegistrationCompletedMail($registration)
        );
    }

    private function handleFailure(MemberRegistration $registration, \Throwable $e): void
    {
        Log::error('Member registration pipeline failed', [
            'registration_id' => $registration->id,
            'status' => $registration->status->value,
            'error' => $e->getMessage(),
        ]);

        $registration->error_message = 'Erreur : '.$e->getMessage();
        $registration->failed_at = Carbon::now('Europe/Zurich')->utc();
        $registration->save();

        Mail::to(User::getMemberRegistrationEmails())->send(
            new MemberRegistrationError($registration)
        );
    }
}
