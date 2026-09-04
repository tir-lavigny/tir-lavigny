<?php

namespace Tests\Unit\Http\Controllers\BackOffice\Register;

use App\Http\Controllers\BackOffice\Register\MemberController;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Response;
use Tests\TestCase;

class MemberControllerTest extends TestCase
{
    use RefreshDatabase;

    private MemberController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new MemberController;
    }

    public function test_index_returns_inertia_response(): void
    {
        Member::factory()->count(3)->create();

        $response = $this->controller->index();

        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_create_returns_inertia_response(): void
    {
        $response = $this->controller->create();

        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_member_model_can_be_created(): void
    {
        $member = Member::create([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean@example.com',
            'is_swiss' => true,
        ]);

        $this->assertDatabaseHas('members', [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => 'jean@example.com',
            'is_swiss' => true,
        ]);
    }

    public function test_member_model_can_be_updated(): void
    {
        $member = Member::factory()->create(['first_name' => 'Jean']);

        $member->update(['first_name' => 'Pierre']);

        $this->assertDatabaseHas('members', ['id' => $member->id, 'first_name' => 'Pierre']);
    }

    public function test_member_model_soft_deletes(): void
    {
        $member = Member::factory()->create();

        $member->delete();

        $this->assertSoftDeleted('members', ['id' => $member->id]);
    }
}
