<?php

namespace App\Listeners;

use App\Events\MemberRegistrationCompleted;
use App\Events\MemberRegistrationDavCreated;
use App\Mail\MemberRegistrationError;
use App\MemberRegistrationStatus;
use App\Models\Member;
use App\Models\Product;
use App\Models\Season;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\Mail;

class CreateMemberAndVouchers
{
    public function handle(MemberRegistrationDavCreated $event): void
    {
        $registration = $event->registration;
        $formData = $registration->form_data;

        try {
            $member = Member::create([
                'first_name' => $formData['first_name'],
                'last_name' => $formData['last_name'],
                'dav_uid' => $registration->dav_uid,
            ]);

            $registration->member()->associate($member);
            $registration->status = MemberRegistrationStatus::MEMBER_CREATED;
            $registration->save();

            $season = Season::where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->first();

            if ($season) {
                $this->generateBaseVouchers($member, $season);
            }

            MemberRegistrationCompleted::dispatch($registration);
        } catch (\Throwable $e) {
            $registration->error_message = 'Erreur membre : '.$e->getMessage();
            $registration->failed_at = now();
            $registration->save();

            Mail::to(User::getMemberRegistrationEmails())->send(new MemberRegistrationError($registration));
        }
    }

    private function generateBaseVouchers(Member $member, Season $season): void
    {
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
}
