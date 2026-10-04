<?php

namespace Tests\Feature\BackOffice;

use App\Mail\MemberRegistrationValidationRequested;
use App\MemberRegistrationStatus;
use App\Models\Member;
use App\Models\MemberRegistration;
use App\Models\User;
use App\Services\DavServiceInterface;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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
        $admin->assignRole('admin');
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
        $davMock->shouldReceive('createContactFromMemberRegistration')
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
            'status' => MemberRegistrationStatus::FORM_FILLED,
            'error_message' => 'Erreur : Mail server down',
            'failed_at' => now(),
        ]);

        $response = $this->post(route('bo.member-registrations.validate', $registration));

        $response->assertRedirect(route('bo.member-registrations.show', $registration));
        $response->assertSessionHas('success', 'Inscription validée, traitement lancé.');

        $registration->refresh();
        $this->assertEquals(MemberRegistrationStatus::COMPLETED, $registration->status);
        $this->assertEquals('validated-uid-123', $registration->dav_uid);
        $this->assertNotNull($registration->member_id);
        $this->assertNull($registration->failed_at);
        $this->assertNull($registration->error_message);
    }

    public function test_validate_rejects_registration_not_awaiting_validation(): void
    {
        $registration = MemberRegistration::create([
            'status' => MemberRegistrationStatus::COMPLETED,
        ]);

        $response = $this->post(route('bo.member-registrations.validate', $registration));

        $response->assertStatus(403);
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

    public function test_retry_on_form_filled_resends_validation_email(): void
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

        Mail::assertSent(MemberRegistrationValidationRequested::class);
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
}
