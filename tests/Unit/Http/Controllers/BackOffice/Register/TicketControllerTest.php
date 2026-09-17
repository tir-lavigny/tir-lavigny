<?php

namespace Tests\Unit\Http\Controllers\BackOffice\Register;

use App\Http\Controllers\BackOffice\Register\TicketController;
use App\Models\OperatingDay;
use App\Models\Season;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketControllerTest extends TestCase
{
    use RefreshDatabase;

    private TicketController $controller;

    private TicketService $ticketService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new TicketController;
        $this->ticketService = $this->app->make(TicketService::class);
    }

    public function test_refund_soft_deletes_ticket(): void
    {
        $season = Season::factory()->create();
        $day = OperatingDay::factory()->create(['season_id' => $season->id]);
        $ticket = Ticket::factory()->cash()->create([
            'operating_day_id' => $day->id,
            'total' => 25.00,
        ]);

        $this->controller->refund($ticket, $this->ticketService);

        $this->assertSoftDeleted('tickets', ['id' => $ticket->id]);
    }

    public function test_refund_recalculates_day_totals(): void
    {
        $season = Season::factory()->create();
        $day = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'total_cash' => 0,
            'total_twint' => 0,
        ]);
        Ticket::factory()->cash()->create([
            'operating_day_id' => $day->id,
            'total' => 50.00,
        ]);

        $day->recalculateTotals();
        $this->assertEquals('50.00', $day->fresh()->total_cash);

        $ticket = Ticket::where('operating_day_id', $day->id)->first();
        $this->controller->refund($ticket, $this->ticketService);

        $day->refresh();
        $this->assertEquals('0.00', $day->total_cash);
    }
}
