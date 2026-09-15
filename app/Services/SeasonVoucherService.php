<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Product;
use App\Models\Season;
use App\Models\Voucher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SeasonVoucherService
{
    /**
     * Get the base voucher package: product name => required count.
     */
    /** @return array<string, int> */
    public function getBasePackage(): array
    {
        return [
            'Tir du jeudi' => 3,
            'Ouverture' => 1,
            'Clôture' => 1,
        ];
    }

    /**
     * Resolve product names to Product models. Aborts 422 if any product not found.
     */
    /** @param array<string, int> $basePackage
     * @return array<string, Product> */
    public function resolveProducts(array $basePackage): array
    {
        $products = [];

        foreach ($basePackage as $name => $count) {
            $product = Product::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();

            if (! $product) {
                abort(422, "Produit introuvable : {$name}");
            }

            $products[$name] = $product;
        }

        return $products;
    }

    /**
     * Compute the voucher plan: which members need which vouchers.
     *
     * @return array<int, array{member_id: int, member_name: string, vouchers: array<int, array{product_name: string, count: int}>}>
     */
    /** @param array<string, int> $basePackage
     * @param array<string, Product> $products
     * @return array<int, array{member_id: int, member_name: string, vouchers: array<int, array{product_name: string, count: int}>}> */
    public function computePlan(Season $season, array $basePackage, array $products): array
    {
        $members = Member::all();

        return $this->computePlanForMembers($season, $members, $basePackage, $products);
    }

    /**
     * Generate base vouchers for all members based on the computed plan.
     */
    /** @param array<string, int> $basePackage */
    public function generateBaseVouchers(Season $season, array $basePackage): void
    {
        $products = $this->resolveProducts($basePackage);
        $plan = $this->computePlan($season, $basePackage, $products);

        DB::transaction(function () use ($plan, $season) {
            foreach ($plan as $memberPlan) {
                foreach ($memberPlan['vouchers'] as $voucherPlan) {
                    if ($voucherPlan['count'] <= 0) {
                        continue;
                    }

                    $product = Product::where('name', $voucherPlan['product_name'])->first();

                    for ($i = 0; $i < $voucherPlan['count']; $i++) {
                        Voucher::create([
                            'discount_percent' => 100.00,
                            'discount_fixed' => null,
                            'product_id' => $product->id,
                            'season_id' => $season->id,
                            'member_id' => $memberPlan['member_id'],
                        ]);
                    }
                }
            }
        });
    }

    /** @param Collection<int, Member> $members
     * @param array<string, int> $basePackage
     * @param array<string, Product> $products
     * @return array<int, array{member_id: int, member_name: string, vouchers: array<int, array{product_name: string, count: int}>}> */
    private function computePlanForMembers(Season $season, Collection $members, array $basePackage, array $products): array
    {
        $plan = [];

        foreach ($members as $member) {
            $vouchers = [];

            foreach ($basePackage as $productName => $requiredCount) {
                $product = $products[$productName];

                $existingCount = Voucher::where('member_id', $member->id)
                    ->where('season_id', $season->id)
                    ->where('product_id', $product->id)
                    ->withTrashed()
                    ->count();

                $missing = $requiredCount - $existingCount;

                if ($missing > 0) {
                    $vouchers[] = [
                        'product_name' => $productName,
                        'count' => $missing,
                    ];
                }
            }

            if (count($vouchers) > 0) {
                $plan[] = [
                    'member_id' => $member->id,
                    'member_name' => $member->first_name.' '.$member->last_name,
                    'vouchers' => $vouchers,
                ];
            }
        }

        return $plan;
    }
}
