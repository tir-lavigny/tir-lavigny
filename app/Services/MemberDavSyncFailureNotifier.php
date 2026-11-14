<?php

namespace App\Services;

use App\Mail\MemberDavSyncFailed;
use App\Models\Member;
use App\Models\MemberRegistration;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MemberDavSyncFailureNotifier
{
    public function notify(Member $member, ?MemberRegistration $registration = null, ?string $error = null): void
    {
        $error ??= $member->dav_sync_error;

        if ($error === null) {
            return;
        }

        try {
            Log::warning('Member DAV sync failure notification', [
                'member_id' => $member->id,
                'registration_id' => $registration?->id,
                'error' => $error,
            ]);

            Mail::to(User::getMemberRegistrationEmails())->send(
                new MemberDavSyncFailed(
                    $registration,
                    $member,
                    'La synchronisation du contact a échoué. Consultez les journaux pour le détail.',
                )
            );
        } catch (\Throwable $e) {
            report($e);

            Log::error('Member DAV sync failure email failed', [
                'registration_id' => $registration?->id,
                'member_id' => $member->id,
                'exception' => $e,
            ]);
        }
    }
}
