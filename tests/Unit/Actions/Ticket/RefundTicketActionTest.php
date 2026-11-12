<?php

namespace Tests\Unit\Actions\Ticket;

use App\Actions\Ticket\RefundTicketAction;
use App\Models\OperatingDay;
use App\Models\Season;
use App\Models\StockLocation;
use App\Models\Ticket;
use App\Services\TicketService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class RefundTicketActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    private function createOpenDay(): OperatingDay
    {
        $season = Season::factory()->create([
            'start_date' => Carbon::now()->startOfYear(),
            'end_date' => Carbon::now()->endOfYear(),
        ]);

        return OperatingDay::factory()->create([
            'season_id' => $season->id,
            'closed_at' => null,
            'date' => Carbon::today('Europe/Zurich')->toDateString(),
            'total_cash' => 0,
            'total_twint' => 0,
        ]);
    }

    public function test_execute_soft_deletes_ticket(): void
    {
        Log::spy();

        $day = $this->createOpenDay();
        $location = StockLocation::factory()->create();
        $ticket = Ticket::factory()->cash()->create([
            'operating_day_id' => $day->id,
            'total' => 25.00,
            'stock_location_id' => $location->id,
        ]);

        $day->update(['total_cash' => 25.00, 'total_twint' => 0]);

        $action = $this->app->make(RefundTicketAction::class);
        $action->execute($ticket);

        $this->assertSoftDeleted('tickets', ['id' => $ticket->id]);

        Log::shouldHaveReceived('info')->withArgs(
            fn (string $message, array $context): bool => $message === 'Ticket refunded'
                && $context['ticket_id'] === $ticket->id
                && $context['operating_day_id'] === $day->id
                && (float) $context['total'] === 25.0
        );
    }

    public function test_execute_delegates_to_ticket_service(): void
    {
        $day = $this->createOpenDay();
        $location = StockLocation::factory()->create();
        $ticket = Ticket::factory()->cash()->create([
            'operating_day_id' => $day->id,
            'total' => 25.00,
            'stock_location_id' => $location->id,
        ]);

        $service = $this->app->make(TicketService::class);
        $spy = Mockery::mock(TicketService::class)->makePartial();
        $spy->shouldReceive('refund')
            ->andReturnUsing(fn ($t) => $service->refund($t));

        $action = new RefundTicketAction($spy);
        $action->execute($ticket);

        $spy->shouldHaveReceived('refund')->with($ticket);
        $this->assertSoftDeleted('tickets', ['id' => $ticket->id]);
    }

    public function test_execute_rolls_back_on_failure(): void
    {
        $day = $this->createOpenDay();
        $location = StockLocation::factory()->create();
        $ticket = Ticket::factory()->cash()->create([
            'operating_day_id' => $day->id,
            'total' => 25.00,
            'stock_location_id' => $location->id,
        ]);

        $spy = Mockery::mock(TicketService::class);
        $spy->shouldReceive('refund')->andReturnUsing(function ($t) {
            $t->delete();
            throw new \Exception('failure');
        });

        $action = new RefundTicketAction($spy);

        try {
            $action->execute($ticket);
        } catch (\Exception $e) {
            $this->assertSame('failure', $e->getMessage());
        }

        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'deleted_at' => null,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
