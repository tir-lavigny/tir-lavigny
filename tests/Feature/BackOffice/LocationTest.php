<?php

namespace Tests\Feature\BackOffice;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->actingAs($user);
    }

    public function test_can_create_location(): void
    {
        $data = [
            'name' => 'Test Location',
            'address' => '123 Test Street',
            'latitude' => 45.12345,
            'longitude' => -75.12345,
        ];
        $response = $this->post(route('locations.store'), $data);

        $response->assertStatus(302);

        $this->assertDatabaseHas('locations', $data);
    }

    public function test_can_not_create_location_with_invalid_data(): void
    {
        $data = [
            'name' => '',
            'address' => '',
            'latitude' => '',
            'longitude' => '',
        ];

        $response = $this->post(route('locations.store'), $data);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['name', 'address', 'latitude', 'longitude']);
    }

    public function test_cannot_create_location_with_invalid_data(): void
    {
        $data = [
            'name' => '', // Invalid name
            'address' => '', // Invalid address
            'latitude' => 'invalid', // Invalid latitude
            'longitude' => 'invalid', // Invalid longitude
        ];

        $response = $this->post(route('locations.store'), $data);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['name', 'address', 'latitude', 'longitude']);
    }

    public function test_cannot_create_location_with_duplicate_name(): void
    {
        Location::factory()->create(['name' => 'Unique Location']);

        $data = [
            'name' => 'Unique Location', // Duplicate name
            'address' => 'Another Address',
            'latitude' => 45.12345,
            'longitude' => -75.12345,
        ];

        $response = $this->post(route('locations.store'), $data);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['name']);
    }

    public function test_unauthorized_user_cannot_access_locations(): void
    {
        // log out the user
        $this->post('/logout');

        $response = $this->get(route('locations.index'));

        $response->assertRedirect('/login');
        $response->assertStatus(302);
    }

    public function test_can_read_locations(): void
    {
        $location = Location::factory()->create();

        $response = $this->get(route('locations.index'));

        $response->assertStatus(200);
        $response->assertSee($location->name);
    }

    public function test_can_update_location(): void
    {
        $location = Location::factory()->create();

        $data = [
            'name' => 'Updated Location',
            'address' => '456 Updated Street',
            'latitude' => 46.12345,
            'longitude' => -76.12345,
        ];

        $response = $this->put(route('locations.update', $location->id), $data);

        $response->assertStatus(302);
        $this->assertDatabaseHas('locations', $data);
    }

    public function test_cannot_update_non_existing_location(): void
    {
        $data = [
            'name' => 'Non-Existing Location',
            'address' => '123 Non-Existing Street',
            'latitude' => 45.12345,
            'longitude' => -75.12345,
        ];

        $response = $this->put(route('locations.update', 9999), $data); // ID 9999 does not exist

        $response->assertStatus(404);
    }

    public function test_can_delete_location(): void
    {
        $location = Location::factory()->create();

        $response = $this->delete(route('locations.destroy', $location->id));

        $response->assertStatus(302);
        $this->assertDatabaseMissing('locations', ['id' => $location->id]);
    }
}
