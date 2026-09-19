<?php

namespace Tests\Unit\Models;

use App\Models\Ticket;
use App\Models\TicketItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class TicketItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_item_is_not_findable_after_soft_delete(): void
    {
        $ticketItem = TicketItem::factory()->create();
        $ticketItemId = $ticketItem->id;

        $ticketItem->delete();

        $this->assertNull(TicketItem::find($ticketItemId));
    }

    public function test_ticket_item_is_findable_with_trashed_after_soft_delete(): void
    {
        $ticketItem = TicketItem::factory()->create();
        $ticketItemId = $ticketItem->id;

        $ticketItem->delete();

        $this->assertNotNull(TicketItem::withTrashed()->find($ticketItemId));
    }

    public function test_ticket_item_cannot_be_permanently_deleted(): void
    {
        $ticketItem = TicketItem::factory()->create();

        $this->expectException(LogicException::class);
        $ticketItem->forceDelete();
    }

    public function test_ticket_item_cannot_be_restored_after_soft_delete(): void
    {
        $ticketItem = TicketItem::factory()->create();

        $ticketItem->delete();

        $this->expectException(LogicException::class);
        $ticketItem->restore();
    }

    public function test_ticket_item_belongs_to_ticket(): void
    {
        $ticket = Ticket::factory()->create();
        $ticketItem = TicketItem::factory()->create(['ticket_id' => $ticket->id]);

        $this->assertInstanceOf(Ticket::class, $ticketItem->ticket);
    }
}
