<?php

namespace Tests\Feature\BackOffice\Register;

use App\Models\Category;
use App\Models\Member;
use App\Models\Product;
use App\Models\Season;
use App\Models\TicketItem;
use App\Models\User;
use App\Models\Voucher;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VoucherControllerTest extends TestCase
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

    public function test_index_returns_vouchers(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $season = Season::factory()->create();
        Voucher::factory()->percent()->create([
            'product_id' => $product->id,
            'season_id' => $season->id,
        ]);

        $response = $this->get(route('bo.register.vouchers.index'));

        $response->assertOk();
    }

    public function test_index_returns_newest_vouchers_first(): void
    {
        $oldVoucher = Voucher::factory()->percent()->create();
        $newVoucher = Voucher::factory()->percent()->create();

        $response = $this->get(route('bo.register.vouchers.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('vouchers.data.0.id', $newVoucher->id)
            ->where('vouchers.data.1.id', $oldVoucher->id)
        );
    }

    public function test_create_sorts_members_by_first_name(): void
    {
        Member::factory()->create(['first_name' => 'Zoe', 'last_name' => 'Bernard']);
        Member::factory()->create(['first_name' => 'Alice', 'last_name' => 'Zurcher']);

        $response = $this->get(route('bo.register.vouchers.create'));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('members.0.first_name', 'Alice')
            ->where('members.1.first_name', 'Zoe')
        );
    }

    public function test_store_creates_voucher(): void
    {
        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $season = Season::factory()->create();
        $member = Member::factory()->create();

        $response = $this->post(route('bo.register.vouchers.store'), [
            'discount_percent' => 15.00,
            'product_id' => $product->id,
            'season_id' => $season->id,
            'member_id' => $member->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vouchers', [
            'discount_percent' => 15.00,
            'product_id' => $product->id,
            'season_id' => $season->id,
            'member_id' => $member->id,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->post(route('bo.register.vouchers.store'), []);

        $response->assertSessionHasErrors(['product_id', 'season_id', 'member_id']);
    }

    public function test_edit_returns_form(): void
    {
        $voucher = Voucher::factory()->percent()->create();

        $response = $this->get(route('bo.register.vouchers.edit', $voucher));

        $response->assertOk();
    }

    public function test_update_modifies_voucher(): void
    {
        $voucher = Voucher::factory()->percent()->create();

        $response = $this->put(route('bo.register.vouchers.update', $voucher), [
            'discount_percent' => 25.00,
            'product_id' => $voucher->product_id,
            'season_id' => $voucher->season_id,
            'member_id' => $voucher->member_id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vouchers', ['id' => $voucher->id, 'discount_percent' => 25.00]);
    }

    public function test_destroy_soft_deletes_voucher(): void
    {
        $voucher = Voucher::factory()->percent()->create();

        $response = $this->delete(route('bo.register.vouchers.destroy', $voucher));

        $response->assertRedirect();
        $this->assertSoftDeleted('vouchers', ['id' => $voucher->id]);
    }

    public function test_store_requires_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view register');
        $this->actingAs($user);

        $category = Category::factory()->create(['is_restricted' => false]);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $season = Season::factory()->create();
        $member = Member::factory()->create();

        $response = $this->post(route('bo.register.vouchers.store'), [
            'discount_percent' => 10.00,
            'product_id' => $product->id,
            'season_id' => $season->id,
            'member_id' => $member->id,
        ]);

        $response->assertForbidden();
    }

    // ── Regression: used vouchers cannot be updated or deleted ──────────

    public function test_update_fails_when_voucher_is_used(): void
    {
        $voucher = Voucher::factory()->percent()->create([
            'discount_percent' => 10.00,
        ]);

        TicketItem::factory()->create([
            'voucher_id' => $voucher->id,
        ]);

        $response = $this->put(route('bo.register.vouchers.update', $voucher), [
            'discount_percent' => 25.00,
            'product_id' => $voucher->product_id,
            'season_id' => $voucher->season_id,
            'member_id' => $voucher->member_id,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'discount_percent' => 10.00,
        ]);
    }

    public function test_destroy_fails_when_voucher_is_used(): void
    {
        $voucher = Voucher::factory()->percent()->create();

        TicketItem::factory()->create([
            'voucher_id' => $voucher->id,
        ]);

        $response = $this->delete(route('bo.register.vouchers.destroy', $voucher));

        $response->assertStatus(422);
        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'deleted_at' => null,
        ]);
    }

    public function test_update_succeeds_when_voucher_is_unused(): void
    {
        $voucher = Voucher::factory()->percent()->create([
            'discount_percent' => 10.00,
        ]);

        $response = $this->put(route('bo.register.vouchers.update', $voucher), [
            'discount_percent' => 25.00,
            'product_id' => $voucher->product_id,
            'season_id' => $voucher->season_id,
            'member_id' => $voucher->member_id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('vouchers', [
            'id' => $voucher->id,
            'discount_percent' => 25.00,
        ]);
    }

    public function test_destroy_succeeds_when_voucher_is_unused(): void
    {
        $voucher = Voucher::factory()->percent()->create();

        $response = $this->delete(route('bo.register.vouchers.destroy', $voucher));

        $response->assertRedirect();
        $this->assertSoftDeleted('vouchers', ['id' => $voucher->id]);
    }
}
