<?php

namespace Tests\Unit\Http\Controllers\BackOffice;

use App\Http\Controllers\BackOffice\MemberController;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
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

        $response = $this->controller->index(new Request);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_index_accepts_archived_filter(): void
    {
        Member::factory()->archived()->create();

        $response = $this->controller->index(new Request(['archived' => '1']));

        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_index_accepts_status_filter(): void
    {
        Member::factory()->passive()->create();

        $response = $this->controller->index(new Request(['status' => 'passive']));

        $this->assertInstanceOf(Response::class, $response);
    }
}
