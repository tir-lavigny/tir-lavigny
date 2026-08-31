<?php

namespace Tests\Feature\BackOffice\Register;

use App\Models\Category;
use App\Models\Member;
use App\Models\Product;
use App\Models\Season;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\User;
use App\Models\Voucher;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrashControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);
    }

    public function test_index_returns_trash_page(): void
    {
        $response = $this->get(route('bo.register.trash.index'));

        $response->assertOk();
    }

    public function test_index_shows_trashed_categories(): void
    {
        $category = Category::factory()->create();
        $category->delete();

        $response = $this->get(route('bo.register.trash.index'));

        $response->assertOk();
        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }

    public function test_index_shows_trashed_products(): void
    {
        $product = Product::factory()->create();
        $product->delete();

        $response = $this->get(route('bo.register.trash.index'));

        $response->assertOk();
    }

    public function test_index_shows_trashed_seasons(): void
    {
        $season = Season::factory()->create();
        $season->delete();

        $response = $this->get(route('bo.register.trash.index'));

        $response->assertOk();
    }

    public function test_index_shows_trashed_members(): void
    {
        $member = Member::factory()->create();
        $member->delete();

        $response = $this->get(route('bo.register.trash.index'));

        $response->assertOk();
    }

    public function test_index_shows_trashed_tickets(): void
    {
        $ticket = Ticket::factory()->create();
        $ticket->delete();

        $response = $this->get(route('bo.register.trash.index'));

        $response->assertOk();
    }

    public function test_index_shows_trashed_vouchers(): void
    {
        $voucher = Voucher::factory()->create();
        $voucher->delete();

        $response = $this->get(route('bo.register.trash.index'));

        $response->assertOk();
    }

    public function test_index_filters_vouchers_by_member(): void
    {
        $member = Member::factory()->create();
        $voucher = Voucher::factory()->create(['member_id' => $member->id]);
        $voucher->delete();

        $otherMember = Member::factory()->create();
        $otherVoucher = Voucher::factory()->create(['member_id' => $otherMember->id]);
        $otherVoucher->delete();

        $response = $this->get(route('bo.register.trash.index') . '?member_id=' . $member->id);

        $response->assertOk();
    }

    public function test_restore_preview_returns_cascade_info(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        $category->delete();

        $response = $this->postJson(route('bo.register.trash.restore', ['type' => 'category', 'id' => $category->id]));

        $response->assertOk();
        $response->assertJsonStructure([
            'item' => ['id', 'name', 'type', 'deleted_at'],
            'will_restore',
        ]);
    }

    public function test_restore_preview_returns_correct_children_counts(): void
    {
        $category = Category::factory()->create();
        Product::factory()->count(2)->create(['category_id' => $category->id]);
        $category->delete();

        $response = $this->postJson(route('bo.register.trash.restore', ['type' => 'category', 'id' => $category->id]));

        $response->assertOk();
        $response->assertJson([
            'will_restore' => [
                ['type' => 'product', 'count' => 2, 'label' => '2 produit(s) supprimé(s)'],
            ],
        ]);
    }

    public function test_restore_with_confirm_restores_category_and_products(): void
    {
        $category = Category::factory()->create();
        Product::factory()->count(2)->create(['category_id' => $category->id]);
        $category->delete();

        $this->assertSoftDeleted('categories', ['id' => $category->id]);

        $response = $this->postJson(route('bo.register.trash.restore', ['type' => 'category', 'id' => $category->id, 'confirm' => true]));

        $response->assertOk();
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('products', ['category_id' => $category->id, 'deleted_at' => null]);
    }

    public function test_restore_ticket_also_restores_ticket_items(): void
    {
        $ticket = Ticket::factory()->create();
        $ticketItem = TicketItem::factory()->create(['ticket_id' => $ticket->id]);
        $ticket->delete();

        $this->assertSoftDeleted('tickets', ['id' => $ticket->id]);
        $this->assertSoftDeleted('ticket_items', ['id' => $ticketItem->id]);

        $response = $this->postJson(route('bo.register.trash.restore', ['type' => 'ticket', 'id' => $ticket->id, 'confirm' => true]));

        $response->assertOk();
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('ticket_items', ['id' => $ticketItem->id, 'deleted_at' => null]);
    }

    public function test_restore_member_restores_unused_vouchers(): void
    {
        $member = Member::factory()->create();
        $voucher = Voucher::factory()->create(['member_id' => $member->id]);
        $member->delete();

        $this->assertSoftDeleted('members', ['id' => $member->id]);
        $this->assertSoftDeleted('vouchers', ['id' => $voucher->id]);

        $response = $this->postJson(route('bo.register.trash.restore', ['type' => 'member', 'id' => $member->id, 'confirm' => true]));

        $response->assertOk();
        $this->assertDatabaseHas('members', ['id' => $member->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('vouchers', ['id' => $voucher->id, 'deleted_at' => null]);
    }

    public function test_member_restore_excludes_used_vouchers(): void
    {
        $member = Member::factory()->create();
        $unusedVoucher = Voucher::factory()->create(['member_id' => $member->id]);
        $usedVoucher = Voucher::factory()->create(['member_id' => $member->id]);
        $ticket = Ticket::factory()->create();
        TicketItem::factory()->create(['ticket_id' => $ticket->id, 'voucher_id' => $usedVoucher->id]);
        $member->delete();

        $response = $this->postJson(route('bo.register.trash.restore', ['type' => 'member', 'id' => $member->id, 'confirm' => true]));

        $response->assertOk();
        $this->assertDatabaseHas('members', ['id' => $member->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('vouchers', ['id' => $unusedVoucher->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('vouchers', ['id' => $usedVoucher->id, 'deleted_at' => null]);
    }

    public function test_restore_returns_404_for_nonexistent_item(): void
    {
        $response = $this->postJson(route('bo.register.trash.restore', ['type' => 'category', 'id' => 999999]));

        $response->assertNotFound();
    }

    public function test_restore_returns_422_for_invalid_type(): void
    {
        $response = $this->postJson(route('bo.register.trash.restore', ['type' => 'invalid', 'id' => 1]));

        $response->assertStatus(422);
    }

    public function test_force_delete_allows_leaf_nodes(): void
    {
        $product = Product::factory()->create();
        $product->delete();

        $response = $this->deleteJson(route('bo.register.trash.force-delete', ['type' => 'product', 'id' => $product->id]));

        $response->assertOk();
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_force_delete_product_allowed_when_no_children(): void
    {
        $product = Product::factory()->create();
        $product->delete();

        $response = $this->deleteJson(route('bo.register.trash.force-delete', ['type' => 'product', 'id' => $product->id]));

        $response->assertOk();
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_force_delete_blocks_category_with_products(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);
        $category->delete();

        $response = $this->deleteJson(route('bo.register.trash.force-delete', ['type' => 'category', 'id' => $category->id]));

        $response->assertStatus(422);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_force_delete_blocks_ticket_with_items(): void
    {
        $ticket = Ticket::factory()->create();
        TicketItem::factory()->create(['ticket_id' => $ticket->id]);
        $ticket->delete();

        $response = $this->deleteJson(route('bo.register.trash.force-delete', ['type' => 'ticket', 'id' => $ticket->id]));

        $response->assertStatus(422);
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id]);
    }

    public function test_force_delete_blocks_member_with_vouchers(): void
    {
        $member = Member::factory()->create();
        Voucher::factory()->create(['member_id' => $member->id]);
        $member->delete();

        $response = $this->deleteJson(route('bo.register.trash.force-delete', ['type' => 'member', 'id' => $member->id]));

        $response->assertStatus(422);
        $this->assertDatabaseHas('members', ['id' => $member->id]);
    }

    public function test_force_delete_season_blocks_with_operating_days(): void
    {
        $season = Season::factory()->create();
        \App\Models\OperatingDay::factory()->create(['season_id' => $season->id]);
        $season->delete();

        $response = $this->deleteJson(route('bo.register.trash.force-delete', ['type' => 'season', 'id' => $season->id]));

        $response->assertStatus(422);
        $this->assertDatabaseHas('seasons', ['id' => $season->id]);
    }

    public function test_force_delete_season_blocks_with_vouchers(): void
    {
        $season = Season::factory()->create();
        $member = Member::factory()->create();
        Voucher::factory()->create(['season_id' => $season->id, 'member_id' => $member->id]);
        $season->delete();

        $response = $this->deleteJson(route('bo.register.trash.force-delete', ['type' => 'season', 'id' => $season->id]));

        $response->assertStatus(422);
        $this->assertDatabaseHas('seasons', ['id' => $season->id]);
    }

    public function test_force_delete_product_with_active_ticket_items_throws(): void
    {
        $product = Product::factory()->create();
        $ticket = Ticket::factory()->create();
        TicketItem::factory()->create(['product_id' => $product->id, 'ticket_id' => $ticket->id]);
        $product->delete();

        $response = $this->deleteJson(route('bo.register.trash.force-delete', ['type' => 'product', 'id' => $product->id]));

        $response->assertStatus(422);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_force_delete_voucher_is_allowed(): void
    {
        $voucher = Voucher::factory()->create();
        $voucher->delete();

        $response = $this->deleteJson(route('bo.register.trash.force-delete', ['type' => 'voucher', 'id' => $voucher->id]));

        $response->assertOk();
        $this->assertDatabaseMissing('vouchers', ['id' => $voucher->id]);
    }

    public function test_requires_restore_register_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole('cashier');
        $this->actingAs($user);

        $response = $this->get(route('bo.register.trash.index'));

        $response->assertForbidden();
    }

    public function test_requires_restore_register_permission_for_restore(): void
    {
        $user = User::factory()->create();
        $user->assignRole('cashier');
        $this->actingAs($user);

        $category = Category::factory()->create();
        $category->delete();

        $response = $this->postJson(route('bo.register.trash.restore', ['type' => 'category', 'id' => $category->id]));

        $response->assertForbidden();
    }

    public function test_requires_restore_register_permission_for_force_delete(): void
    {
        $user = User::factory()->create();
        $user->assignRole('cashier');
        $this->actingAs($user);

        $product = Product::factory()->create();
        $product->delete();

        $response = $this->deleteJson(route('bo.register.trash.force-delete', ['type' => 'product', 'id' => $product->id]));

        $response->assertForbidden();
    }
}
