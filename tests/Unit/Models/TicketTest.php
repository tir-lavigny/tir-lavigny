<?php

namespace Tests\Unit\Models;

use App\Models\Ticket;
use App\Models\TicketItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_is_not_findable_after_soft_delete(): void
    {
        $ticket = Ticket::factory()->create();
        $ticketId = $ticket->id;

        $ticket->delete();

        $this->assertNull(Ticket::find($ticketId));
    }

    public function test_ticket_is_findable_with_trashed_after_soft_delete(): void
    {
        $ticket = Ticket::factory()->create();
        $ticketId = $ticket->id;

        $ticket->delete();

        $this->assertNotNull(Ticket::withTrashed()->find($ticketId));
    }

    public function test_ticket_belongs_to_operating_day(): void
    {
        $ticket = Ticket::factory()->create();

        $this->assertNotNull($ticket->operatingDay);
    }

    public function test_ticket_has_many_ticket_items(): void
    {
        $ticket = Ticket::factory()->create();
        TicketItem::factory()->count(3)->create(['ticket_id' => $ticket->id]);

        $this->assertCount(3, $ticket->ticketItems);
    }

    public function test_ticket_items_are_soft_deleted_when_ticket_is_deleted(): void
    {
        $ticket = Ticket::factory()->create();
        TicketItem::factory()->count(2)->create(['ticket_id' => $ticket->id]);

        $ticket->delete();

        $this->assertDatabaseCount('ticket_items', 2);
        $this->assertTrue($ticket->ticketItems()->withTrashed()->first()->trashed());
    }

    public function test_ticket_items_are_restored_when_ticket_is_restored(): void
    {
        $ticket = Ticket::factory()->create();
        TicketItem::factory()->count(2)->create(['ticket_id' => $ticket->id]);

        $ticket->delete();
        $ticket->restore();

        $this->assertFalse($ticket->ticketItems()->withTrashed()->first()->trashed());
    }
}
