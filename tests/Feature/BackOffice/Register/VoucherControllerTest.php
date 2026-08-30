<?php

namespace Tests\Feature\BackOffice\Register;

use App\Models\Category;
use App\Models\Member;
use App\Models\Product;
use App\Models\Season;
use App\Models\User;
use App\Models\Voucher;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
