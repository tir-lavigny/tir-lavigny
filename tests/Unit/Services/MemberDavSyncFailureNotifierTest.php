<?php

namespace Tests\Unit\Services;

use App\Mail\MemberDavSyncFailed;
use App\Models\Member;
use App\Models\User;
use App\Services\MemberDavSyncFailureNotifier;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MemberDavSyncFailureNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_does_not_include_raw_dav_response_body(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        User::factory()->create()->assignRole('get member registration mails');

        Mail::fake();

        $member = Member::factory()->create();

        (new MemberDavSyncFailureNotifier)->notify(
            $member,
            null,
            'DAV PUT failed with status 500: <html>secret-contact-data</html>',
        );

        Mail::assertSent(MemberDavSyncFailed::class, function (MemberDavSyncFailed $mail): bool {
            return ! str_contains($mail->error, 'secret-contact-data');
        });
    }
}
