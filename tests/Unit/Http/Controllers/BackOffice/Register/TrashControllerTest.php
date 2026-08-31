<?php

namespace Tests\Unit\Http\Controllers\BackOffice\Register;

use App\Http\Controllers\BackOffice\Register\TrashController;
use App\Models\Category;
use App\Models\Member;
use App\Models\Product;
use App\Models\Season;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TrashControllerTest extends TestCase
{
    use RefreshDatabase;

    private TrashController $controller;

    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new TrashController;
        $this->reflection = new ReflectionClass($this->controller);
    }

    private function callProtected(string $method, array $args = []): mixed
    {
        $method = $this->reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($this->controller, $args);
    }

    public function test_resolve_type_returns_correct_class(): void
    {
        $this->assertEquals(Category::class, $this->callProtected('resolveType', ['category']));
        $this->assertEquals(Product::class, $this->callProtected('resolveType', ['product']));
        $this->assertEquals(Season::class, $this->callProtected('resolveType', ['season']));
        $this->assertEquals(Member::class, $this->callProtected('resolveType', ['member']));
        $this->assertEquals(Ticket::class, $this->callProtected('resolveType', ['ticket']));
        $this->assertEquals(Voucher::class, $this->callProtected('resolveType', ['voucher']));
    }

    public function test_resolve_type_throws_for_invalid_type(): void
    {
        $this->expectException(HttpException::class);
        $this->callProtected('resolveType', ['invalid']);
    }

    public function test_get_item_label_returns_category_name(): void
    {
        $category = Category::factory()->create(['name' => 'Shooting Range']);

        $label = $this->callProtected('getItemLabel', [$category, 'category']);

        $this->assertEquals('Shooting Range', $label);
    }

    public function test_get_item_label_returns_product_name(): void
    {
        $product = Product::factory()->create(['name' => 'Cartridge']);

        $label = $this->callProtected('getItemLabel', [$product, 'product']);

        $this->assertEquals('Cartridge', $label);
    }

    public function test_get_item_label_returns_season_name(): void
    {
        $season = Season::factory()->create(['name' => '2024-2025']);

        $label = $this->callProtected('getItemLabel', [$season, 'season']);

        $this->assertEquals('2024-2025', $label);
    }

    public function test_get_item_label_returns_member_full_name(): void
    {
        $member = Member::factory()->create(['first_name' => 'Jean', 'last_name' => 'Dupont']);

        $label = $this->callProtected('getItemLabel', [$member, 'member']);

        $this->assertEquals('Jean Dupont', $label);
    }

    public function test_get_item_label_returns_ticket_id(): void
    {
        $ticket = Ticket::factory()->create();

        $label = $this->callProtected('getItemLabel', [$ticket, 'ticket']);

        $this->assertEquals('Ticket #'.$ticket->id, $label);
    }

    public function test_get_item_label_returns_voucher_id(): void
    {
        $voucher = Voucher::factory()->create();

        $label = $this->callProtected('getItemLabel', [$voucher, 'voucher']);

        $this->assertEquals('Bon #'.$voucher->id, $label);
    }

    public function test_get_children_for_category_returns_empty_when_no_children(): void
    {
        $category = Category::factory()->create();

        $children = $this->callProtected('getChildrenCounts', [$category, 'category']);

        $this->assertEmpty($children);
    }

    public function test_get_children_for_category_counts_trashed_products(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);
        $product->delete();

        $children = $this->callProtected('getChildrenCounts', [$category, 'category']);

        $this->assertCount(1, $children);
        $this->assertEquals('product', $children[0]['type']);
        $this->assertEquals(1, $children[0]['count']);
        $this->assertStringContainsString('supprimé', $children[0]['label']);
    }

    public function test_get_children_for_category_counts_active_products(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        $children = $this->callProtected('getChildrenCounts', [$category, 'category']);

        $this->assertCount(1, $children);
        $this->assertEquals('product', $children[0]['type']);
        $this->assertEquals(1, $children[0]['count']);
        $this->assertStringContainsString('actif', $children[0]['label']);
    }

    public function test_get_children_for_ticket_returns_empty_when_no_children(): void
    {
        $ticket = Ticket::factory()->create();

        $children = $this->callProtected('getChildrenCounts', [$ticket, 'ticket']);

        $this->assertEmpty($children);
    }

    public function test_get_children_for_ticket_counts_trashed_items(): void
    {
        $ticket = Ticket::factory()->create();
        $ticketItem = TicketItem::factory()->create(['ticket_id' => $ticket->id]);
        $ticketItem->delete();

        $children = $this->callProtected('getChildrenCounts', [$ticket, 'ticket']);

        $this->assertCount(1, $children);
        $this->assertEquals('ticket_item', $children[0]['type']);
        $this->assertEquals(1, $children[0]['count']);
        $this->assertStringContainsString('supprimé', $children[0]['label']);
    }

    public function test_get_children_for_ticket_counts_active_items(): void
    {
        $ticket = Ticket::factory()->create();
        TicketItem::factory()->create(['ticket_id' => $ticket->id]);

        $children = $this->callProtected('getChildrenCounts', [$ticket, 'ticket']);

        $this->assertCount(1, $children);
        $this->assertEquals('ticket_item', $children[0]['type']);
        $this->assertEquals(1, $children[0]['count']);
        $this->assertStringContainsString('actif', $children[0]['label']);
    }

    public function test_get_children_for_member_returns_empty_when_no_children(): void
    {
        $member = Member::factory()->create();

        $children = $this->callProtected('getChildrenCounts', [$member, 'member']);

        $this->assertEmpty($children);
    }

    public function test_get_children_for_member_counts_trashed_vouchers(): void
    {
        $member = Member::factory()->create();
        $voucher = Voucher::factory()->create(['member_id' => $member->id]);
        $voucher->delete();

        $children = $this->callProtected('getChildrenCounts', [$member, 'member']);

        $this->assertCount(1, $children);
        $this->assertEquals('voucher', $children[0]['type']);
        $this->assertEquals(1, $children[0]['count']);
        $this->assertStringContainsString('supprimé', $children[0]['label']);
    }

    public function test_get_children_for_member_counts_active_vouchers(): void
    {
        $member = Member::factory()->create();
        Voucher::factory()->create(['member_id' => $member->id]);

        $children = $this->callProtected('getChildrenCounts', [$member, 'member']);

        $this->assertCount(1, $children);
        $this->assertEquals('voucher', $children[0]['type']);
        $this->assertEquals(1, $children[0]['count']);
        $this->assertStringContainsString('actif', $children[0]['label']);
    }

    public function test_get_children_for_product_returns_empty(): void
    {
        $product = Product::factory()->create();

        $children = $this->callProtected('getChildrenCounts', [$product, 'product']);

        $this->assertEmpty($children);
    }

    public function test_get_children_for_season_returns_empty(): void
    {
        $season = Season::factory()->create();

        $children = $this->callProtected('getChildrenCounts', [$season, 'season']);

        $this->assertEmpty($children);
    }

    public function test_get_children_for_voucher_returns_empty(): void
    {
        $voucher = Voucher::factory()->create();

        $children = $this->callProtected('getChildrenCounts', [$voucher, 'voucher']);

        $this->assertEmpty($children);
    }
}
