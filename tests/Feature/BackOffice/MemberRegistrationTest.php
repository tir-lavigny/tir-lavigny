<?php

namespace Tests\Feature\BackOffice;

use App\Mail\MemberRegistrationEmailVerification;
use App\Mail\MemberRegistrationValidationRequested;
use App\MemberRegistrationStatus;
use App\Models\Member;
use App\Models\MemberRegistration;
use App\Models\User;
use App\Services\DavServiceInterface;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Tests\TestCase;

class MemberRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin', 'get member registration mails');
        $this->actingAs($admin);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_can_view_registration_list(): void
    {
        $response = $this->get(route('bo.member-registrations.index'));

        $response->assertStatus(200);
    }

    public function test_registration_list_renders_with_legacy_plaintext_avs(): void
    {
        $member = Member::factory()->create();
        DB::table('members')->where('id', $member->id)->update(['avs' => '756.0000.0000.00']);
        MemberRegistration::create(['member_id' => $member->id]);

        $response = $this->get(route('bo.member-registrations.index'));

        $response->assertStatus(200);
    }

    public function test_can_view_specific_registration(): void
    {
        $registration = MemberRegistration::create();

        $response = $this->get(route('bo.member-registrations.show', $registration));

        $response->assertStatus(200);
    }

    public function test_can_create_registration(): void
    {
        $response = $this->post(route('bo.member-registrations.store'));

        $response->assertStatus(302);
        $this->assertDatabaseHas('member_registrations', [
            'status' => MemberRegistrationStatus::PENDING->value,
        ]);
    }

    public function test_store_creates_registration_with_uuid(): void
    {
        $response = $this->post(route('bo.member-registrations.store'));

        $response->assertStatus(302);
        $registration = MemberRegistration::latest()->first();
        $this->assertNotEmpty($registration->form_uuid);
    }

    public function test_store_redirects_to_show_page(): void
    {
        $response = $this->post(route('bo.member-registrations.store'));

        $registration = MemberRegistration::latest()->first();
        $response->assertRedirect(route('bo.member-registrations.show', $registration));
    }

    public function test_store_sets_flash_success_message(): void
    {
        $response = $this->post(route('bo.member-registrations.store'));

        $response->assertSessionHas('success', 'Formulaire créé avec succès.');
    }

    public function test_index_filters_by_status(): void
    {
        MemberRegistration::create(['status' => MemberRegistrationStatus::PENDING]);
        MemberRegistration::create(['status' => MemberRegistrationStatus::COMPLETED]);

        $response = $this->get(route('bo.member-registrations.index', ['status' => 'pending']));

        $response->assertStatus(200);
        $response->assertSee(MemberRegistrationStatus::PENDING->value);
        $response->assertDontSee(MemberRegistrationStatus::COMPLETED->value);
    }

    public function test_index_shows_all_statuses_when_no_filter(): void
    {
        MemberRegistration::create(['status' => MemberRegistrationStatus::PENDING]);
        MemberRegistration::create(['status' => MemberRegistrationStatus::COMPLETED]);

        $response = $this->get(route('bo.member-registrations.index'));

        $response->assertStatus(200);
    }

    public function test_show_displays_registration_details(): void
    {
        $member = Member::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
        $registration = MemberRegistration::create([
            'form_data' => ['email' => 'john@example.com'],
        ]);
        $registration->member()->associate($member);
        $registration->save();

        $response = $this->get(route('bo.member-registrations.show', $registration));

        $response->assertStatus(200);
        $response->assertSee('John');
        $response->assertSee('Doe');
        $response->assertSee('john@example.com');
    }

    public function test_show_exposes_verification_resend_cooldown(): void
    {
        $this->freezeTime();

        $registration = $this->registrationWithAvs('756.0000.0000.02');
        $registration->email_verified_at = null;
        $registration->email_verification_sent_at = now()->subSeconds(20);
        $registration->save();

        $response = $this->get(route('bo.member-registrations.show', $registration));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('resendAvailableIn', 40)
            ->where('emailVerificationSentAt', $registration->fresh()->email_verification_sent_at->toJSON())
        );
    }

    public function test_show_reports_no_cooldown_once_email_is_verified(): void
    {
        $this->freezeTime();

        $registration = $this->registrationWithAvs('756.0000.0000.03');
        $registration->email_verification_sent_at = now()->subSeconds(10);
        $registration->save();

        $response = $this->get(route('bo.member-registrations.show', $registration));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('resendAvailableIn', 0)
        );
    }

    public function test_show_reports_no_cooldown_without_verification_code(): void
    {
        $registration = $this->registrationWithAvs('756.0000.0000.04');
        $registration->email_verified_at = null;
        $registration->save();

        $response = $this->get(route('bo.member-registrations.show', $registration));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('resendAvailableIn', 0)
            ->where('emailVerificationSentAt', null)
        );
    }

    public function test_show_does_not_expose_dav_uid(): void
    {
        $registration = MemberRegistration::create();

        $response = $this->get(route('bo.member-registrations.show', $registration));

        $response->assertInertia(fn ($page) => $page
            ->missing('registration.dav_uid')
        );
    }

    public function test_show_displays_error_message_when_present(): void
    {
        $registration = MemberRegistration::create([
            'error_message' => 'DAV creation failed',
        ]);

        $response = $this->get(route('bo.member-registrations.show', $registration));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('backOffice/MemberRegistrations/Show')
            ->has('registration')
            ->where('registration.error_message', 'DAV creation failed')
        );
    }

    public function test_show_displays_form_url(): void
    {
        $registration = MemberRegistration::create();

        $response = $this->get(route('bo.member-registrations.show', $registration));

        $response->assertStatus(200);
        $response->assertSee($registration->form_uuid);
    }

    public function test_guest_is_redirected_to_login_on_index(): void
    {
        $this->post('/logout');

        $response = $this->get(route('bo.member-registrations.index'));
        $response->assertRedirect('/login');
    }

    public function test_guest_is_redirected_to_login_on_show(): void
    {
        $this->post('/logout');

        $registration = MemberRegistration::create();
        $response = $this->get(route('bo.member-registrations.show', $registration));
        $response->assertRedirect('/login');
    }

    public function test_guest_is_redirected_to_login_on_store(): void
    {
        $this->post('/logout');

        $response = $this->post(route('bo.member-registrations.store'));
        $response->assertRedirect('/login');
    }

    public function test_cannot_view_nonexistent_registration(): void
    {
        $response = $this->get(route('bo.member-registrations.show', 9999));

        $response->assertStatus(404);
    }

    public function test_index_pagination_works(): void
    {
        for ($i = 0; $i < 20; $i++) {
            MemberRegistration::create();
        }

        $response = $this->get(route('bo.member-registrations.index', ['page' => 2]));

        $response->assertStatus(200);
    }

    public function test_store_with_form_data(): void
    {
        $response = $this->post(route('bo.member-registrations.store'));

        $response->assertStatus(302);
        $registration = MemberRegistration::latest()->first();
        $this->assertNull($registration->form_data);
    }

    public function test_validate_runs_pipeline_for_form_filled_registration(): void
    {
        Mail::fake();

        $davMock = Mockery::mock(DavServiceInterface::class);
        $davMock->shouldReceive('createContactFromMember')
            ->once()
            ->andReturn(['uid' => 'validated-uid-123']);
        $this->app->instance(DavServiceInterface::class, $davMock);

        $registration = MemberRegistration::create([
            'form_uuid' => Str::uuid(),
            'form_data' => [
                'first_name' => 'Jean',
                'last_name' => 'Dupont',
                'email' => 'jean.dupont@example.com',
            ],
            'form_submitted_at' => now(),
            'email_verified_at' => now(),
            'status' => MemberRegistrationStatus::FORM_FILLED,
            'error_message' => 'Erreur : Mail server down',
            'failed_at' => now(),
        ]);

        $response = $this->post(route('bo.member-registrations.validate', $registration));

        $response->assertRedirect(route('bo.member-registrations.show', $registration));
        $response->assertSessionHas('success', 'Inscription validée, traitement lancé.');

        $registration->refresh();
        $this->assertEquals(MemberRegistrationStatus::COMPLETED, $registration->status);
        $this->assertSame('validated-uid-123', $registration->member->dav_uid);
        $this->assertNotNull($registration->member_id);
        $this->assertNull($registration->failed_at);
        $this->assertNull($registration->error_message);
    }

    public function test_show_displays_avs_conflict(): void
    {
        [$member, $registration] = $this->duplicateAvsRegistration();

        $response = $this->get(route('bo.member-registrations.show', $registration));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('avsConflict.member_id', $member->id)
            ->where('avsConflict.member_name', trim($member->first_name.' '.$member->last_name))
            ->where('avsConflict.avs', '756.1234.5678.90')
            ->where('canEditAvs', true)
        );
    }

    public function test_show_has_no_conflict_for_unique_avs(): void
    {
        $registration = $this->registrationWithAvs('756.0000.0000.00');

        $response = $this->get(route('bo.member-registrations.show', $registration));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('avsConflict', null)
        );
    }

    public function test_show_masks_conflict_avs_without_sensitive_permission(): void
    {
        [$member, $registration] = $this->duplicateAvsRegistration();

        $user = User::factory()->create();
        $user->assignRole('member manager');
        $this->actingAs($user);

        $response = $this->get(route('bo.member-registrations.show', $registration));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('avsConflict.member_id', $member->id)
            ->where('avsConflict.avs', '756.***.***.90')
            ->where('canEditAvs', false)
        );
    }

    public function test_completed_registration_does_not_conflict_with_itself(): void
    {
        $member = Member::factory()->create(['avs' => '756.1234.5678.90']);
        $registration = $this->registrationWithAvs('756.1234.5678.90', MemberRegistrationStatus::COMPLETED);
        $registration->member()->associate($member);
        $registration->save();

        $response = $this->get(route('bo.member-registrations.show', $registration));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('avsConflict', null)
        );
    }

    public function test_validate_is_blocked_while_avs_conflicts(): void
    {
        [, $registration] = $this->duplicateAvsRegistration();

        $response = $this->post(route('bo.member-registrations.validate', $registration));

        $response->assertRedirect(route('bo.member-registrations.show', $registration));
        $response->assertSessionHas('error');
        $this->assertSame(MemberRegistrationStatus::FORM_FILLED, $registration->fresh()->status);
        $this->assertSame(1, Member::count());
    }

    public function test_retry_is_blocked_while_avs_conflicts(): void
    {
        [, $registration] = $this->duplicateAvsRegistration(MemberRegistrationStatus::ERROR);

        $response = $this->post(route('bo.member-registrations.retry', $registration));

        $response->assertRedirect(route('bo.member-registrations.show', $registration));
        $response->assertSessionHas('error');
        $this->assertSame(MemberRegistrationStatus::ERROR, $registration->fresh()->status);
    }

    public function test_update_avs_clears_conflict_and_preserves_form_data(): void
    {
        [, $registration] = $this->duplicateAvsRegistration();

        $response = $this->patch(route('bo.member-registrations.update-avs', $registration), [
            'avs' => '756.0000.0000.00',
        ]);

        $response->assertRedirect(route('bo.member-registrations.show', $registration));
        $response->assertSessionHasNoErrors();

        $registration->refresh();

        $this->assertSame('756.0000.0000.00', $registration->form_data['avs']);
        $this->assertSame('Jean', $registration->form_data['first_name']);
        $this->assertSame('Dupont', $registration->form_data['last_name']);
    }

    public function test_validate_succeeds_after_registration_avs_is_corrected(): void
    {
        [, $registration] = $this->duplicateAvsRegistration();
        $this->mockSuccessfulDav();

        $this->patch(route('bo.member-registrations.update-avs', $registration), [
            'avs' => '756.0000.0000.00',
        ]);

        $this->post(route('bo.member-registrations.validate', $registration));

        $registration->refresh();

        $this->assertSame(MemberRegistrationStatus::COMPLETED, $registration->status);
        $this->assertNotNull($registration->member_id);
    }

    public function test_validate_succeeds_after_member_avs_is_corrected(): void
    {
        [$member, $registration] = $this->duplicateAvsRegistration();
        $this->mockSuccessfulDav();

        $member->update(['avs' => '756.0000.0000.00']);

        $this->post(route('bo.member-registrations.validate', $registration));

        $registration->refresh();

        $this->assertSame(MemberRegistrationStatus::COMPLETED, $registration->status);
        $this->assertNotNull($registration->member_id);
    }

    public function test_update_avs_rejects_another_duplicate(): void
    {
        [, $registration] = $this->duplicateAvsRegistration();
        Member::factory()->create(['avs' => '756.0000.0000.00']);

        $response = $this->patch(route('bo.member-registrations.update-avs', $registration), [
            'avs' => '756.0000.0000.00',
        ]);

        $response->assertSessionHasErrors('avs');
        $this->assertSame('756.1234.5678.90', $registration->fresh()->form_data['avs']);
    }

    public function test_update_avs_requires_sensitive_permission(): void
    {
        [, $registration] = $this->duplicateAvsRegistration();

        $user = User::factory()->create();
        $user->givePermissionTo('access backoffice', 'manage member registration');
        $this->actingAs($user);

        $response = $this->patch(route('bo.member-registrations.update-avs', $registration), [
            'avs' => '756.0000.0000.00',
        ]);

        $response->assertForbidden();
    }

    public function test_conflict_clears_when_member_avs_changes(): void
    {
        [$member, $registration] = $this->duplicateAvsRegistration();

        $member->update(['avs' => '756.0000.0000.00']);

        $response = $this->get(route('bo.member-registrations.show', $registration));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('avsConflict', null)
        );
    }

    public function test_validate_rejects_registration_not_awaiting_validation(): void
    {
        $registration = MemberRegistration::create([
            'status' => MemberRegistrationStatus::COMPLETED,
        ]);

        $response = $this->post(route('bo.member-registrations.validate', $registration));

        $response->assertStatus(403);
    }

    public function test_validate_is_blocked_while_email_is_not_verified(): void
    {
        $registration = $this->registrationWithAvs('756.0000.0000.01');
        $registration->email_verified_at = null;
        $registration->save();

        $response = $this->post(route('bo.member-registrations.validate', $registration));

        $response->assertStatus(403);
        $this->assertSame(MemberRegistrationStatus::FORM_FILLED, $registration->fresh()->status);
        $this->assertNull($registration->fresh()->member_id);
    }

    public function test_validate_requires_manage_member_registration_permission(): void
    {
        $this->post('/logout');

        $userWithoutPermission = User::factory()->create();
        $this->actingAs($userWithoutPermission);

        $registration = MemberRegistration::create([
            'status' => MemberRegistrationStatus::FORM_FILLED,
        ]);

        $response = $this->post(route('bo.member-registrations.validate', $registration));

        $response->assertForbidden();
    }

    public function test_retry_on_form_filled_resends_verification_code_when_unverified(): void
    {
        Mail::fake();

        $registration = MemberRegistration::create([
            'form_uuid' => Str::uuid(),
            'form_data' => [
                'first_name' => 'Jean',
                'last_name' => 'Dupont',
                'email' => 'jean.dupont@example.com',
            ],
            'form_submitted_at' => now(),
            'status' => MemberRegistrationStatus::FORM_FILLED,
            'error_message' => 'Erreur : Mail server down',
            'failed_at' => now(),
        ]);

        $response = $this->post(route('bo.member-registrations.retry', $registration));

        $response->assertRedirect(route('bo.member-registrations.show', $registration));

        $registration->refresh();
        $this->assertEquals(MemberRegistrationStatus::FORM_FILLED, $registration->status);
        $this->assertNull($registration->failed_at);
        $this->assertNull($registration->error_message);

        Mail::assertSent(MemberRegistrationEmailVerification::class);
        Mail::assertNotSent(MemberRegistrationValidationRequested::class);
    }

    public function test_retry_on_form_filled_resends_validation_email_when_verified(): void
    {
        Mail::fake();

        $registration = MemberRegistration::create([
            'form_uuid' => Str::uuid(),
            'form_data' => [
                'first_name' => 'Jean',
                'last_name' => 'Dupont',
                'email' => 'jean.dupont@example.com',
            ],
            'form_submitted_at' => now(),
            'email_verified_at' => now(),
            'status' => MemberRegistrationStatus::FORM_FILLED,
            'error_message' => 'Erreur : Mail server down',
            'failed_at' => now(),
        ]);

        $response = $this->post(route('bo.member-registrations.retry', $registration));

        $response->assertRedirect(route('bo.member-registrations.show', $registration));

        Mail::assertSent(MemberRegistrationValidationRequested::class);
        Mail::assertNotSent(MemberRegistrationEmailVerification::class);
    }

    public function test_destroy_deletes_unsubmitted_registration(): void
    {
        $registration = MemberRegistration::create([
            'status' => MemberRegistrationStatus::PENDING,
        ]);

        $response = $this->delete(route('bo.member-registrations.destroy', $registration));

        $response->assertRedirect(route('bo.member-registrations.index'));
        $response->assertSessionHas('success', 'Lien supprimé avec succès.');
        $this->assertDatabaseMissing('member_registrations', ['id' => $registration->id]);
    }

    public function test_destroy_rejects_submitted_registration(): void
    {
        $registration = MemberRegistration::create([
            'form_submitted_at' => now(),
            'status' => MemberRegistrationStatus::FORM_FILLED,
        ]);

        $response = $this->delete(route('bo.member-registrations.destroy', $registration));

        $response->assertStatus(403);
        $this->assertDatabaseHas('member_registrations', ['id' => $registration->id]);
    }

    public function test_destroy_requires_manage_member_registration_permission(): void
    {
        $this->post('/logout');

        $userWithoutPermission = User::factory()->create();
        $this->actingAs($userWithoutPermission);

        $registration = MemberRegistration::create([
            'status' => MemberRegistrationStatus::PENDING,
        ]);

        $response = $this->delete(route('bo.member-registrations.destroy', $registration));

        $response->assertForbidden();
        $this->assertDatabaseHas('member_registrations', ['id' => $registration->id]);
    }

    public function test_guest_is_redirected_to_login_on_destroy(): void
    {
        $this->post('/logout');

        $registration = MemberRegistration::create([
            'status' => MemberRegistrationStatus::PENDING,
        ]);

        $response = $this->delete(route('bo.member-registrations.destroy', $registration));

        $response->assertRedirect('/login');
        $this->assertDatabaseHas('member_registrations', ['id' => $registration->id]);
    }

    private function mockSuccessfulDav(): void
    {
        $davMock = Mockery::mock(DavServiceInterface::class);
        $davMock->shouldReceive('createContactFromMember')
            ->once()
            ->andReturn([
                'uid' => 'uid-1',
                'url' => 'http://example.com/uid-1.vcf',
                'fn' => 'Dupont Jean',
                'vcard_raw' => 'BEGIN:VCARD',
            ]);
        $this->app->instance(DavServiceInterface::class, $davMock);
    }

    /** @return array{0: Member, 1: MemberRegistration} */
    private function duplicateAvsRegistration(
        MemberRegistrationStatus $status = MemberRegistrationStatus::FORM_FILLED,
    ): array {
        $member = Member::factory()->create(['avs' => '756.1234.5678.90']);

        return [$member, $this->registrationWithAvs('756.1234.5678.90', $status)];
    }

    private function registrationWithAvs(
        string $avs,
        MemberRegistrationStatus $status = MemberRegistrationStatus::FORM_FILLED,
    ): MemberRegistration {
        return MemberRegistration::create([
            'form_uuid' => Str::uuid(),
            'form_data' => [
                'first_name' => 'Jean',
                'last_name' => 'Dupont',
                'email' => 'jean.dupont@example.com',
                'is_swiss_citizen' => true,
                'avs' => $avs,
                'tel' => '+41 21 123 45 67',
                'street' => 'Rue du Marché 1',
                'npa' => '1003',
                'city' => 'Lausanne',
                'canton' => 'VD',
                'country' => 'Suisse',
            ],
            'form_submitted_at' => now(),
            'email_verified_at' => now(),
            'status' => $status,
        ]);
    }
}
