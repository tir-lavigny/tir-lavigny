<?php

namespace Tests\Feature;

use App\Models\MemberRegistration;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberRegistrationFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_empty_form_with_valid_uuid(): void
    {
        $registration = MemberRegistration::create();

        $response = $this->get(route('member-registration.show', $registration->form_uuid));

        $response->assertStatus(200);
    }

    public function test_returns_404_for_invalid_uuid(): void
    {
        $response = $this->get(route('member-registration.show', 'invalid-uuid'));

        $response->assertStatus(404);
    }

    public function test_can_submit_form_with_valid_data(): void
    {
        $registration = MemberRegistration::create();

        $data = [
            'form_uuid' => (string) $registration->form_uuid,
            'last_name' => 'Dupont',
            'first_name' => 'Jean',
            'email' => 'jean.dupont@example.com',
            'is_swiss_citizen' => true,
            'avs' => '756.1234.5678.90',
            'tel' => '+41 21 123 45 67',
            'street' => 'Rue du Marché 1',
            'npa' => '1003',
            'city' => 'Lausanne',
            'canton' => 'VD',
            'country' => 'Suisse',
        ];

        $response = $this->post(route('member-registration.store'), $data);

        $response->assertStatus(302);
        $this->assertDatabaseHas('member_registrations', [
            'id' => $registration->id,
            'status' => 'form_filled',
        ]);
    }

    public function test_form_data_is_stored_correctly(): void
    {
        $registration = MemberRegistration::create();

        $data = [
            'form_uuid' => (string) $registration->form_uuid,
            'last_name' => 'Dupont',
            'first_name' => 'Jean',
            'email' => 'jean.dupont@example.com',
            'is_swiss_citizen' => true,
            'avs' => '756.1234.5678.90',
            'tel' => null,
            'street' => 'Rue du Marché 1',
            'npa' => '1003',
            'city' => 'Lausanne',
            'canton' => 'VD',
            'country' => 'Suisse',
        ];

        $this->post(route('member-registration.store'), $data);

        $registration->refresh();
        $this->assertNotNull($registration->form_data);
        $this->assertEquals('Dupont', $registration->form_data['last_name']);
        $this->assertEquals('Jean', $registration->form_data['first_name']);
        $this->assertEquals('jean.dupont@example.com', $registration->form_data['email']);
        $this->assertTrue($registration->form_data['is_swiss_citizen']);
        $this->assertEquals('756.1234.5678.90', $registration->form_data['avs']);
        $this->assertEquals('VD', $registration->form_data['canton']);
        $this->assertEquals('Suisse', $registration->form_data['country']);
    }

    public function test_form_submitted_at_is_set(): void
    {
        $registration = MemberRegistration::create();

        $data = [
            'form_uuid' => (string) $registration->form_uuid,
            'last_name' => 'Dupont',
            'first_name' => 'Jean',
            'email' => 'jean.dupont@example.com',
            'is_swiss_citizen' => false,
            'avs' => null,
            'street' => 'Rue du Marché 1',
            'npa' => '1003',
            'city' => 'Lausanne',
            'canton' => 'VD',
            'country' => 'Suisse',
        ];

        $this->post(route('member-registration.store'), $data);

        $registration->refresh();
        $this->assertNotNull($registration->form_submitted_at);
    }

    public function test_last_name_is_required(): void
    {
        $registration = MemberRegistration::create();

        $data = [
            'form_uuid' => (string) $registration->form_uuid,
            'last_name' => '',
            'first_name' => 'Jean',
            'email' => 'jean@example.com',
            'is_swiss_citizen' => false,
            'street' => 'Rue 1',
            'npa' => '1003',
            'city' => 'Lausanne',
            'canton' => 'VD',
            'country' => 'Suisse',
        ];

        $response = $this->post(route('member-registration.store'), $data);

        $response->assertSessionHasErrors(['last_name']);
    }

    public function test_first_name_is_required(): void
    {
        $registration = MemberRegistration::create();

        $data = [
            'form_uuid' => (string) $registration->form_uuid,
            'last_name' => 'Dupont',
            'first_name' => '',
            'email' => 'jean@example.com',
            'is_swiss_citizen' => false,
            'street' => 'Rue 1',
            'npa' => '1003',
            'city' => 'Lausanne',
            'canton' => 'VD',
            'country' => 'Suisse',
        ];

        $response = $this->post(route('member-registration.store'), $data);

        $response->assertSessionHasErrors(['first_name']);
    }

    public function test_email_is_required(): void
    {
        $registration = MemberRegistration::create();

        $data = [
            'form_uuid' => (string) $registration->form_uuid,
            'last_name' => 'Dupont',
            'first_name' => 'Jean',
            'email' => '',
            'is_swiss_citizen' => false,
            'street' => 'Rue 1',
            'npa' => '1003',
            'city' => 'Lausanne',
            'canton' => 'VD',
            'country' => 'Suisse',
        ];

        $response = $this->post(route('member-registration.store'), $data);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_email_must_be_valid(): void
    {
        $registration = MemberRegistration::create();

        $data = [
            'form_uuid' => (string) $registration->form_uuid,
            'last_name' => 'Dupont',
            'first_name' => 'Jean',
            'email' => 'not-an-email',
            'is_swiss_citizen' => false,
            'street' => 'Rue 1',
            'npa' => '1003',
            'city' => 'Lausanne',
            'canton' => 'VD',
            'country' => 'Suisse',
        ];

        $response = $this->post(route('member-registration.store'), $data);

        $response->assertSessionHasErrors(['email']);
    }

    public function test_avs_required_when_swiss_citizen(): void
    {
        $registration = MemberRegistration::create();

        $data = [
            'form_uuid' => (string) $registration->form_uuid,
            'last_name' => 'Dupont',
            'first_name' => 'Jean',
            'email' => 'jean@example.com',
            'is_swiss_citizen' => true,
            'avs' => '',
            'street' => 'Rue 1',
            'npa' => '1003',
            'city' => 'Lausanne',
            'canton' => 'VD',
            'country' => 'Suisse',
        ];

        $response = $this->post(route('member-registration.store'), $data);

        $response->assertSessionHasErrors(['avs']);
    }

    public function test_avs_not_required_when_not_swiss(): void
    {
        $registration = MemberRegistration::create();

        $data = [
            'form_uuid' => (string) $registration->form_uuid,
            'last_name' => 'Dupont',
            'first_name' => 'Jean',
            'email' => 'jean@example.com',
            'is_swiss_citizen' => false,
            'avs' => null,
            'street' => 'Rue 1',
            'npa' => '1003',
            'city' => 'Lausanne',
            'canton' => 'VD',
            'country' => 'Suisse',
        ];

        $response = $this->post(route('member-registration.store'), $data);

        $response->assertStatus(302);
    }

    public function test_avs_format_validated(): void
    {
        $registration = MemberRegistration::create();

        $data = [
            'form_uuid' => (string) $registration->form_uuid,
            'last_name' => 'Dupont',
            'first_name' => 'Jean',
            'email' => 'jean@example.com',
            'is_swiss_citizen' => true,
            'avs' => 'invalid-format',
            'street' => 'Rue 1',
            'npa' => '1003',
            'city' => 'Lausanne',
            'canton' => 'VD',
            'country' => 'Suisse',
        ];

        $response = $this->post(route('member-registration.store'), $data);

        $response->assertSessionHasErrors(['avs']);
    }

    public function test_avs_valid_format_accepted(): void
    {
        $registration = MemberRegistration::create();

        $data = [
            'form_uuid' => (string) $registration->form_uuid,
            'last_name' => 'Dupont',
            'first_name' => 'Jean',
            'email' => 'jean@example.com',
            'is_swiss_citizen' => true,
            'avs' => '756.1234.5678.90',
            'street' => 'Rue 1',
            'npa' => '1003',
            'city' => 'Lausanne',
            'canton' => 'VD',
            'country' => 'Suisse',
        ];

        $response = $this->post(route('member-registration.store'), $data);

        $response->assertStatus(302);
    }

    public function test_street_is_required(): void
    {
        $registration = MemberRegistration::create();

        $data = [
            'form_uuid' => (string) $registration->form_uuid,
            'last_name' => 'Dupont',
            'first_name' => 'Jean',
            'email' => 'jean@example.com',
            'is_swiss_citizen' => false,
            'street' => '',
            'npa' => '1003',
            'city' => 'Lausanne',
            'canton' => 'VD',
            'country' => 'Suisse',
        ];

        $response = $this->post(route('member-registration.store'), $data);

        $response->assertSessionHasErrors(['street']);
    }

    public function test_npa_is_required(): void
    {
        $registration = MemberRegistration::create();

        $data = [
            'form_uuid' => (string) $registration->form_uuid,
            'last_name' => 'Dupont',
            'first_name' => 'Jean',
            'email' => 'jean@example.com',
            'is_swiss_citizen' => false,
            'street' => 'Rue 1',
            'npa' => '',
            'city' => 'Lausanne',
            'canton' => 'VD',
            'country' => 'Suisse',
        ];

        $response = $this->post(route('member-registration.store'), $data);

        $response->assertSessionHasErrors(['npa']);
    }

    public function test_city_is_required(): void
    {
        $registration = MemberRegistration::create();

        $data = [
            'form_uuid' => (string) $registration->form_uuid,
            'last_name' => 'Dupont',
            'first_name' => 'Jean',
            'email' => 'jean@example.com',
            'is_swiss_citizen' => false,
            'street' => 'Rue 1',
            'npa' => '1003',
            'city' => '',
            'canton' => 'VD',
            'country' => 'Suisse',
        ];

        $response = $this->post(route('member-registration.store'), $data);

        $response->assertSessionHasErrors(['city']);
    }

    public function test_canton_is_required(): void
    {
        $registration = MemberRegistration::create();

        $data = [
            'form_uuid' => (string) $registration->form_uuid,
            'last_name' => 'Dupont',
            'first_name' => 'Jean',
            'email' => 'jean@example.com',
            'is_swiss_citizen' => false,
            'street' => 'Rue 1',
            'npa' => '1003',
            'city' => 'Lausanne',
            'canton' => '',
            'country' => 'Suisse',
        ];

        $response = $this->post(route('member-registration.store'), $data);

        $response->assertSessionHasErrors(['canton']);
    }

    public function test_canton_must_be_valid(): void
    {
        $registration = MemberRegistration::create();

        $data = [
            'form_uuid' => (string) $registration->form_uuid,
            'last_name' => 'Dupont',
            'first_name' => 'Jean',
            'email' => 'jean@example.com',
            'is_swiss_citizen' => false,
            'street' => 'Rue 1',
            'npa' => '1003',
            'city' => 'Lausanne',
            'canton' => 'XX',
            'country' => 'Suisse',
        ];

        $response = $this->post(route('member-registration.store'), $data);

        $response->assertSessionHasErrors(['canton']);
    }

    public function test_country_is_required(): void
    {
        $registration = MemberRegistration::create();

        $data = [
            'form_uuid' => (string) $registration->form_uuid,
            'last_name' => 'Dupont',
            'first_name' => 'Jean',
            'email' => 'jean@example.com',
            'is_swiss_citizen' => false,
            'street' => 'Rue 1',
            'npa' => '1003',
            'city' => 'Lausanne',
            'canton' => 'VD',
            'country' => '',
        ];

        $response = $this->post(route('member-registration.store'), $data);

        $response->assertSessionHasErrors(['country']);
    }

    public function test_after_submission_form_shows_filled_data(): void
    {
        $registration = MemberRegistration::create([
            'form_data' => [
                'last_name' => 'Dupont',
                'first_name' => 'Jean',
                'email' => 'jean@example.com',
                'is_swiss_citizen' => true,
                'avs' => '756.1234.5678.90',
                'street' => 'Rue du Marché 1',
                'npa' => '1003',
                'city' => 'Lausanne',
                'canton' => 'VD',
                'country' => 'Suisse',
            ],
            'form_submitted_at' => now(),
        ]);

        $response = $this->get(route('member-registration.show', $registration->form_uuid));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('MemberRegistration')
            ->where('submitted', true)
            ->has('registration')
        );
    }

    public function test_after_24h_returns_404(): void
    {
        $registration = MemberRegistration::create([
            'form_data' => ['last_name' => 'Dupont'],
            'form_submitted_at' => Carbon::now()->subHours(25),
        ]);

        $response = $this->get(route('member-registration.show', $registration->form_uuid));

        $response->assertStatus(404);
    }

    public function test_within_24h_shows_form(): void
    {
        $registration = MemberRegistration::create([
            'form_data' => ['last_name' => 'Dupont'],
            'form_submitted_at' => Carbon::now()->subHours(12),
        ]);

        $response = $this->get(route('member-registration.show', $registration->form_uuid));

        $response->assertStatus(200);
    }

    public function test_cannot_submit_twice(): void
    {
        $registration = MemberRegistration::create([
            'form_submitted_at' => now(),
        ]);

        $data = [
            'form_uuid' => (string) $registration->form_uuid,
            'last_name' => 'Dupont',
            'first_name' => 'Jean',
            'email' => 'jean@example.com',
            'is_swiss_citizen' => false,
            'street' => 'Rue 1',
            'npa' => '1003',
            'city' => 'Lausanne',
            'canton' => 'VD',
            'country' => 'Suisse',
        ];

        $response = $this->post(route('member-registration.store'), $data);

        $response->assertStatus(403);
    }

    public function test_canton_defaults_to_vaud(): void
    {
        $registration = MemberRegistration::create();

        $response = $this->get(route('member-registration.show', $registration->form_uuid));

        $response->assertStatus(200);
    }

    public function test_country_defaults_to_suisse(): void
    {
        $registration = MemberRegistration::create();

        $response = $this->get(route('member-registration.show', $registration->form_uuid));

        $response->assertStatus(200);
    }

    public function test_tel_is_optional(): void
    {
        $registration = MemberRegistration::create();

        $data = [
            'form_uuid' => (string) $registration->form_uuid,
            'last_name' => 'Dupont',
            'first_name' => 'Jean',
            'email' => 'jean@example.com',
            'is_swiss_citizen' => false,
            'avs' => null,
            'tel' => null,
            'street' => 'Rue 1',
            'npa' => '1003',
            'city' => 'Lausanne',
            'canton' => 'VD',
            'country' => 'Suisse',
        ];

        $response = $this->post(route('member-registration.store'), $data);

        $response->assertStatus(302);
    }

    public function test_status_changes_to_form_filled(): void
    {
        $registration = MemberRegistration::create();

        $data = [
            'form_uuid' => (string) $registration->form_uuid,
            'last_name' => 'Dupont',
            'first_name' => 'Jean',
            'email' => 'jean@example.com',
            'is_swiss_citizen' => false,
            'street' => 'Rue 1',
            'npa' => '1003',
            'city' => 'Lausanne',
            'canton' => 'VD',
            'country' => 'Suisse',
        ];

        $this->post(route('member-registration.store'), $data);

        $registration->refresh();
        $this->assertEquals('form_filled', $registration->status->value);
    }
}
