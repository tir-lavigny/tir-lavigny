<?php

namespace Tests\Feature\BackOffice;

use App\MemberRegistrationStatus;
use App\Models\Member;
use App\Models\MemberRegistration;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_store_sets_flash_form_url(): void
    {
        $response = $this->post(route('bo.member-registrations.store'));

        $registration = MemberRegistration::latest()->first();
        $response->assertSessionHas('form_url', $registration->formUrl());
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
}
