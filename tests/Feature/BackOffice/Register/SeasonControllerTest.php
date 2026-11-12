<?php

namespace Tests\Feature\BackOffice\Register;

use App\Models\Category;
use App\Models\Member;
use App\Models\OperatingDay;
use App\Models\Product;
use App\Models\Season;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Voucher;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SeasonControllerTest extends TestCase
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

    public function test_index_returns_seasons(): void
    {
        Season::factory()->create(['name' => '2025-2026']);

        $response = $this->get(route('bo.register.seasons.index'));

        $response->assertOk();
    }

    public function test_index_searches_seasons_without_accents(): void
    {
        Season::factory()->create(['name' => 'Été 2026']);
        Season::factory()->create(['name' => 'Hiver 2026']);

        $response = $this->get(route('bo.register.seasons.index', ['search' => 'ete']));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('seasons.data', 1)
            ->where('seasons.data.0.name', 'Été 2026')
            ->where('filters.search', 'ete')
        );
    }

    public function test_store_creates_season(): void
    {
        $response = $this->post(route('bo.register.seasons.store'), [
            'name' => '2026-2027',
            'start_date' => Carbon::now()->startOfYear()->toDateString(),
            'end_date' => Carbon::now()->endOfYear()->toDateString(),
            'initial_balance' => 500.00,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('seasons', ['name' => '2026-2027']);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->post(route('bo.register.seasons.store'), []);

        $response->assertSessionHasErrors(['name', 'start_date', 'end_date', 'initial_balance']);
    }

    public function test_store_validates_end_date_after_start_date(): void
    {
        $response = $this->post(route('bo.register.seasons.store'), [
            'name' => 'Bad Season',
            'start_date' => Carbon::now()->toDateString(),
            'end_date' => Carbon::now()->subMonth()->toDateString(),
            'initial_balance' => 0,
        ]);

        $response->assertSessionHasErrors(['end_date']);
    }

    public function test_show_returns_season(): void
    {
        $season = Season::factory()->create();

        $response = $this->get(route('bo.register.seasons.show', $season));

        $response->assertOk();
    }

    public function test_edit_returns_form(): void
    {
        $season = Season::factory()->create();

        $response = $this->get(route('bo.register.seasons.edit', $season));

        $response->assertOk();
    }

    public function test_update_modifies_season(): void
    {
        $season = Season::factory()->create(['name' => 'Old']);

        $response = $this->put(route('bo.register.seasons.update', $season), [
            'name' => 'New',
            'start_date' => $season->start_date->toDateString(),
            'end_date' => $season->end_date->toDateString(),
            'initial_balance' => $season->initial_balance,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('seasons', ['id' => $season->id, 'name' => 'New']);
    }

    public function test_edit_shares_closed_days_count(): void
    {
        $season = Season::factory()->create();
        OperatingDay::factory()->closed()->create(['season_id' => $season->id]);
        OperatingDay::factory()->closed()->create(['season_id' => $season->id]);
        OperatingDay::factory()->create(['season_id' => $season->id, 'closed_at' => null]);

        $response = $this->get(route('bo.register.seasons.edit', $season));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->where('closedDaysCount', 2));
    }

    public function test_update_initial_balance_cascades_to_closed_days(): void
    {
        $season = Season::factory()->create(['initial_balance' => 1000]);
        $day1 = OperatingDay::factory()->closed()->create([
            'season_id' => $season->id,
            'date' => Carbon::parse('2026-05-01'),
            'total_cash' => 200,
            'closing_balance' => 1200,
        ]);
        $day2 = OperatingDay::factory()->closed()->create([
            'season_id' => $season->id,
            'date' => Carbon::parse('2026-05-02'),
            'total_cash' => 100,
            'closing_balance' => 1300,
        ]);

        $this->put(route('bo.register.seasons.update', $season), [
            'initial_balance' => 1500,
        ])->assertRedirect();

        $this->assertEquals(1700.00, $day1->fresh()->closing_balance);
        $this->assertEquals(1800.00, $day2->fresh()->closing_balance);
        $this->assertDatabaseHas('seasons', ['id' => $season->id, 'initial_balance' => 1500]);
    }

    public function test_update_initial_balance_leaves_open_days_balance_null(): void
    {
        $season = Season::factory()->create(['initial_balance' => 1000]);
        $openDay = OperatingDay::factory()->create([
            'season_id' => $season->id,
            'closed_at' => null,
            'closing_balance' => null,
            'total_cash' => 50,
        ]);

        $this->put(route('bo.register.seasons.update', $season), [
            'initial_balance' => 2000,
        ])->assertRedirect();

        $this->assertNull($openDay->fresh()->getRawOriginal('closing_balance'));
        $this->assertEquals(2050.00, $openDay->fresh()->closing_balance);
    }

    public function test_update_without_initial_balance_change_keeps_stored_closing_balances(): void
    {
        $season = Season::factory()->create(['initial_balance' => 1000]);
        $day = OperatingDay::factory()->closed()->create([
            'season_id' => $season->id,
            'date' => Carbon::parse('2026-05-01'),
            'total_cash' => 200,
            'closing_balance' => 999.99,
        ]);

        $this->put(route('bo.register.seasons.update', $season), [
            'name' => 'Renamed',
            'initial_balance' => 1000,
        ])->assertRedirect();

        $this->assertEquals(999.99, $day->fresh()->closing_balance);
    }

    public function test_update_initial_balance_without_closed_days_succeeds(): void
    {
        $season = Season::factory()->create(['initial_balance' => 1000]);

        $this->put(route('bo.register.seasons.update', $season), [
            'initial_balance' => 300,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals(300.00, $season->fresh()->end_balance);
    }

    public function test_update_rejects_negative_initial_balance(): void
    {
        $season = Season::factory()->create(['initial_balance' => 1000]);

        $this->put(route('bo.register.seasons.update', $season), [
            'initial_balance' => -10,
        ])->assertSessionHasErrors(['initial_balance']);
    }

    public function test_end_balance_matches_last_closed_day_closing_balance(): void
    {
        $season = Season::factory()->create(['initial_balance' => 1000]);
        OperatingDay::factory()->closed()->create([
            'season_id' => $season->id,
            'date' => Carbon::parse('2026-05-01'),
            'total_cash' => 200,
            'closing_balance' => 1200,
        ]);
        $lastDay = OperatingDay::factory()->closed()->create([
            'season_id' => $season->id,
            'date' => Carbon::parse('2026-05-02'),
            'total_cash' => -50,
            'closing_balance' => 1150,
        ]);

        $this->assertEquals(1150.00, $season->fresh()->end_balance);
        $this->assertEquals(1150.00, $lastDay->fresh()->closing_balance);
    }

    public function test_destroy_soft_deletes_season(): void
    {
        $season = Season::factory()->create();

        $response = $this->delete(route('bo.register.seasons.destroy', $season));

        $response->assertRedirect();
        $this->assertSoftDeleted('seasons', ['id' => $season->id]);
    }

    public function test_destroy_blocks_season_with_operating_days(): void
    {
        $season = Season::factory()->create();
        OperatingDay::factory()->create(['season_id' => $season->id]);

        $response = $this->delete(route('bo.register.seasons.destroy', $season));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('seasons', ['id' => $season->id, 'deleted_at' => null]);
    }

    public function test_destroy_blocks_season_with_vouchers(): void
    {
        $season = Season::factory()->create();
        Voucher::factory()->create(['season_id' => $season->id]);

        $response = $this->delete(route('bo.register.seasons.destroy', $season));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('seasons', ['id' => $season->id, 'deleted_at' => null]);
    }

    public function test_store_requires_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view register');
        $this->actingAs($user);

        $response = $this->post(route('bo.register.seasons.store'), [
            'name' => 'Test',
            'start_date' => Carbon::now()->toDateString(),
            'end_date' => Carbon::now()->addYear()->toDateString(),
            'initial_balance' => 0,
        ]);

        $response->assertForbidden();
    }

    public function test_end_balance_is_computed_from_operating_days(): void
    {
        $season = Season::factory()->create(['initial_balance' => 100.00]);
        OperatingDay::factory()->create(['season_id' => $season->id, 'total_cash' => 100.00]);
        OperatingDay::factory()->create(['season_id' => $season->id, 'total_cash' => 200.00]);

        $this->assertEquals(400.00, $season->end_balance);
    }

    public function test_end_balance_returns_zero_when_no_operating_days(): void
    {
        $season = Season::factory()->create(['initial_balance' => 0]);

        $this->assertEquals(0.0, $season->end_balance);
    }

    // ─── Preview Base Vouchers ────────────────────────────────────────────

    public function test_preview_requires_manage_seasons_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view register');
        $this->actingAs($user);

        $season = Season::factory()->create();

        $response = $this->get(route('bo.register.seasons.preview-base-vouchers', $season));

        $response->assertForbidden();
    }

    public function test_preview_returns_plan_for_members_without_vouchers(): void
    {
        $season = Season::factory()->create();
        $this->createBasePackageProducts();
        Member::factory()->count(3)->create();

        $response = $this->get(route('bo.register.seasons.preview-base-vouchers', $season));

        $response->assertOk();
        $response->assertJsonCount(3, 'plan');
        $response->assertJsonPath('total_members', 3);
    }

    public function test_preview_excludes_members_with_all_vouchers(): void
    {
        $season = Season::factory()->create();
        $products = $this->createBasePackageProducts();
        $member = Member::factory()->create();

        Voucher::factory()->percent()->count(3)->create([
            'member_id' => $member->id,
            'season_id' => $season->id,
            'product_id' => $products['Tir du jeudi']->id,
        ]);
        Voucher::factory()->percent()->create([
            'member_id' => $member->id,
            'season_id' => $season->id,
            'product_id' => $products['Ouverture']->id,
        ]);
        Voucher::factory()->percent()->create([
            'member_id' => $member->id,
            'season_id' => $season->id,
            'product_id' => $products['Clôture']->id,
        ]);

        $response = $this->get(route('bo.register.seasons.preview-base-vouchers', $season));

        $response->assertOk();
        $response->assertJsonCount(0, 'plan');
    }

    public function test_preview_calculates_missing_count(): void
    {
        $season = Season::factory()->create();
        $products = $this->createBasePackageProducts();
        $member = Member::factory()->create();

        Voucher::factory()->percent()->create([
            'member_id' => $member->id,
            'season_id' => $season->id,
            'product_id' => $products['Tir du jeudi']->id,
        ]);

        $response = $this->get(route('bo.register.seasons.preview-base-vouchers', $season));

        $response->assertOk();
        $response->assertJsonCount(1, 'plan');
        $response->assertJsonFragment([
            'member_id' => $member->id,
            'vouchers' => [
                ['product_name' => 'Tir du jeudi', 'count' => 2],
                ['product_name' => 'Ouverture', 'count' => 1],
                ['product_name' => 'Clôture', 'count' => 1],
            ],
        ]);
    }

    public function test_preview_handles_soft_deleted_vouchers(): void
    {
        $season = Season::factory()->create();
        $products = $this->createBasePackageProducts();
        $member = Member::factory()->create();

        $voucher = Voucher::factory()->percent()->create([
            'member_id' => $member->id,
            'season_id' => $season->id,
            'product_id' => $products['Ouverture']->id,
        ]);
        $voucher->delete();

        $response = $this->get(route('bo.register.seasons.preview-base-vouchers', $season));

        $response->assertOk();
        $response->assertJsonCount(1, 'plan');
        $response->assertJsonFragment([
            'member_id' => $member->id,
            'vouchers' => [
                ['product_name' => 'Tir du jeudi', 'count' => 3],
                ['product_name' => 'Clôture', 'count' => 1],
            ],
        ]);
    }

    public function test_preview_handles_used_vouchers(): void
    {
        $season = Season::factory()->create();
        $products = $this->createBasePackageProducts();
        $member = Member::factory()->create();

        $voucher = Voucher::factory()->percent()->create([
            'member_id' => $member->id,
            'season_id' => $season->id,
            'product_id' => $products['Clôture']->id,
        ]);
        $ticket = Ticket::factory()->create([
            'operating_day_id' => OperatingDay::factory()->create(['season_id' => $season->id])->id,
            'member_id' => $member->id,
        ]);
        $ticket->ticketItems()->create([
            'product_id' => $products['Clôture']->id,
            'voucher_id' => $voucher->id,
            'quantity' => 1,
            'unit_price' => 5.00,
            'subtotal' => 0.00,
        ]);

        $response = $this->get(route('bo.register.seasons.preview-base-vouchers', $season));

        $response->assertOk();
        $response->assertJsonCount(1, 'plan');
        $response->assertJsonFragment([
            'member_id' => $member->id,
            'vouchers' => [
                ['product_name' => 'Tir du jeudi', 'count' => 3],
                ['product_name' => 'Ouverture', 'count' => 1],
            ],
        ]);
    }

    public function test_preview_does_not_create_vouchers(): void
    {
        $season = Season::factory()->create();
        $this->createBasePackageProducts();
        Member::factory()->count(2)->create();

        $this->get(route('bo.register.seasons.preview-base-vouchers', $season));

        $this->assertDatabaseCount('vouchers', 0);
    }

    public function test_preview_handles_missing_product(): void
    {
        $season = Season::factory()->create();

        $response = $this->get(route('bo.register.seasons.preview-base-vouchers', $season));

        $response->assertStatus(422);
    }

    // ─── Generate Base Vouchers ───────────────────────────────────────────

    public function test_generate_requires_manage_seasons_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view register');
        $this->actingAs($user);

        $season = Season::factory()->create();

        $response = $this->post(route('bo.register.seasons.generate-base-vouchers', $season));

        $response->assertForbidden();
    }

    public function test_generate_creates_vouchers_for_all_members(): void
    {
        $season = Season::factory()->create();
        $this->createBasePackageProducts();
        Member::factory()->count(3)->create();

        $response = $this->post(route('bo.register.seasons.generate-base-vouchers', $season));

        $response->assertRedirect();
        $this->assertDatabaseCount('vouchers', 15);
    }

    public function test_generate_skips_members_with_all_vouchers(): void
    {
        $season = Season::factory()->create();
        $products = $this->createBasePackageProducts();
        $member = Member::factory()->create();

        foreach ($products as $productName => $product) {
            $count = match ($productName) {
                'Tir du jeudi' => 3,
                'Ouverture' => 1,
                'Clôture' => 1,
            };
            Voucher::factory()->percent()->count($count)->create([
                'member_id' => $member->id,
                'season_id' => $season->id,
                'product_id' => $product->id,
            ]);
        }

        $response = $this->post(route('bo.register.seasons.generate-base-vouchers', $season));

        $response->assertRedirect();
        $this->assertDatabaseCount('vouchers', 5);
    }

    public function test_generate_creates_only_missing_vouchers(): void
    {
        $season = Season::factory()->create();
        $products = $this->createBasePackageProducts();
        $member = Member::factory()->create();

        Voucher::factory()->percent()->create([
            'member_id' => $member->id,
            'season_id' => $season->id,
            'product_id' => $products['Ouverture']->id,
        ]);

        $response = $this->post(route('bo.register.seasons.generate-base-vouchers', $season));

        $response->assertRedirect();
        $this->assertDatabaseCount('vouchers', 5);
    }

    public function test_generate_does_not_overcount(): void
    {
        $season = Season::factory()->create();
        $products = $this->createBasePackageProducts();
        $member = Member::factory()->create();

        Voucher::factory()->percent()->count(3)->create([
            'member_id' => $member->id,
            'season_id' => $season->id,
            'product_id' => $products['Tir du jeudi']->id,
        ]);

        $response = $this->post(route('bo.register.seasons.generate-base-vouchers', $season));

        $response->assertRedirect();
        $this->assertDatabaseCount('vouchers', 5);
        $this->assertEquals(3, Voucher::where([
            'product_id' => $products['Tir du jeudi']->id,
            'member_id' => $member->id,
            'season_id' => $season->id,
        ])->count());
    }

    public function test_generate_handles_soft_deleted_vouchers(): void
    {
        $season = Season::factory()->create();
        $products = $this->createBasePackageProducts();
        $member = Member::factory()->create();

        $voucher = Voucher::factory()->percent()->create([
            'member_id' => $member->id,
            'season_id' => $season->id,
            'product_id' => $products['Ouverture']->id,
        ]);
        $voucher->delete();

        $response = $this->post(route('bo.register.seasons.generate-base-vouchers', $season));

        $response->assertRedirect();
        $this->assertEquals(4, Voucher::withoutTrashed()->count());
    }

    public function test_generate_sets_correct_discount_percent(): void
    {
        $season = Season::factory()->create();
        $this->createBasePackageProducts();
        Member::factory()->create();

        $this->post(route('bo.register.seasons.generate-base-vouchers', $season));

        $this->assertDatabaseHas('vouchers', [
            'discount_percent' => 100.00,
            'discount_fixed' => null,
        ]);
    }

    public function test_generate_sets_correct_foreign_keys(): void
    {
        $season = Season::factory()->create();
        $products = $this->createBasePackageProducts();
        $member = Member::factory()->create();

        $this->post(route('bo.register.seasons.generate-base-vouchers', $season));

        foreach ($products as $productName => $product) {
            $count = match ($productName) {
                'Tir du jeudi' => 3,
                'Ouverture' => 1,
                'Clôture' => 1,
            };
            $this->assertEquals($count, Voucher::where([
                'product_id' => $product->id,
                'season_id' => $season->id,
                'member_id' => $member->id,
            ])->count());
        }
    }

    public function test_generate_handles_missing_product(): void
    {
        $season = Season::factory()->create();

        $response = $this->post(route('bo.register.seasons.generate-base-vouchers', $season));

        $response->assertStatus(422);
        $this->assertDatabaseCount('vouchers', 0);
    }

    public function test_generate_no_members_returns_success(): void
    {
        $season = Season::factory()->create();
        $this->createBasePackageProducts();

        $response = $this->post(route('bo.register.seasons.generate-base-vouchers', $season));

        $response->assertRedirect();
        $this->assertDatabaseCount('vouchers', 0);
    }

    private function createBasePackageProducts(): array
    {
        $category = Category::factory()->create(['is_restricted' => false]);

        return [
            'Tir du jeudi' => Product::factory()->create(['name' => 'Tir du jeudi', 'category_id' => $category->id]),
            'Ouverture' => Product::factory()->create(['name' => 'Ouverture', 'category_id' => $category->id]),
            'Clôture' => Product::factory()->create(['name' => 'Clôture', 'category_id' => $category->id]),
        ];
    }
}
