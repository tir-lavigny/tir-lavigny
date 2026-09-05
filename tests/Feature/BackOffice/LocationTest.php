<?php

namespace Tests\Feature\BackOffice;

use App\Models\Event;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationTest extends TestCase
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

        $response->assertStatus(422);
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

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'name' => $location->name,
            'address' => $location->address,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
        ]);
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

    public function test_cannot_delete_location_linked_to_events(): void
    {
        $location = Location::factory()->create();
        Event::factory()->create(['location_id' => $location->id]);

        $response = $this->delete(route('locations.destroy', $location->id));

        $response->assertStatus(422);
        $this->assertDatabaseHas('locations', ['id' => $location->id]);
    }

    public function test_name_is_required(): void
    {
        $data = [
            'name' => '',
            'address' => '123 Test Street',
            'latitude' => 45.12345,
            'longitude' => -75.12345,
        ];

        $response = $this->post(route('locations.store'), $data);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_address_is_required(): void
    {
        $data = [
            'name' => 'Test Location',
            'address' => '',
            'latitude' => 45.12345,
            'longitude' => -75.12345,
        ];

        $response = $this->post(route('locations.store'), $data);

        $response->assertSessionHasErrors(['address']);
    }

    public function test_latitude_is_required(): void
    {
        $data = [
            'name' => 'Test Location',
            'address' => '123 Test Street',
            'latitude' => '',
            'longitude' => -75.12345,
        ];

        $response = $this->post(route('locations.store'), $data);

        $response->assertSessionHasErrors(['latitude']);
    }

    public function test_longitude_is_required(): void
    {
        $data = [
            'name' => 'Test Location',
            'address' => '123 Test Street',
            'latitude' => 45.12345,
            'longitude' => '',
        ];

        $response = $this->post(route('locations.store'), $data);

        $response->assertSessionHasErrors(['longitude']);
    }

    public function test_latitude_and_longitude_must_be_numbers(): void
    {
        $data = [
            'name' => 'Test Location',
            'address' => '123 Test Street',
            'latitude' => 'invalid',
            'longitude' => 'invalid',
        ];

        $response = $this->post(route('locations.store'), $data);

        $response->assertSessionHasErrors(['latitude', 'longitude']);
    }

    public function test_name_and_address_must_be_strings(): void
    {
        $data = [
            'name' => 12345,
            'address' => 67890,
            'latitude' => 45.12345,
            'longitude' => -75.12345,
        ];

        $response = $this->post(route('locations.store'), $data);

        $response->assertSessionHasErrors(['name', 'address']);
    }

    public function test_name_and_address_max_length(): void
    {
        $data = [
            'name' => str_repeat('a', 256), // 256 caractères
            'address' => str_repeat('b', 256), // 256 caractères
            'latitude' => 45.12345,
            'longitude' => -75.12345,
        ];

        $response = $this->post(route('locations.store'), $data);

        $response->assertSessionHasErrors(['name', 'address']);
    }

    public function test_cannot_update_location_with_duplicate_name(): void
    {
        $location1 = Location::factory()->create(['name' => 'Location 1']);
        $location2 = Location::factory()->create(['name' => 'Location 2']);

        $data = [
            'name' => 'Location 1', // Duplicate name
            'address' => $location2->address,
            'latitude' => $location2->latitude,
            'longitude' => $location2->longitude,
        ];

        $response = $this->put(route('locations.update', $location2->id), $data);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['name']);
    }

    public function test_cannot_delete_non_existing_location(): void
    {
        $response = $this->delete(route('locations.destroy', 9999)); // ID 9999 does not exist

        $response->assertStatus(404);
    }
}
