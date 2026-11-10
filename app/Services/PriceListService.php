<?php

namespace App\Services;

use App\Models\PriceList;
use App\Models\PriceListItem;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PriceListService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PriceList
    {
        return DB::transaction(function () use ($data): PriceList {
            $list = PriceList::create([
                'name' => $data['name'],
                'notes' => $data['notes'] ?? null,
            ]);

            $list->items()->createMany($data['items']);

            return $list;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PriceList $list, array $data): PriceList
    {
        return DB::transaction(function () use ($list, $data): PriceList {
            $list->update([
                'name' => $data['name'],
                'notes' => $data['notes'] ?? null,
            ]);

            $list->items()->delete();
            $list->items()->createMany($data['items']);

            return $list;
        });
    }

    public function delete(PriceList $list): void
    {
        $list->delete();
    }

    /**
     * @return array{rows: list<array{product_id: int, product_name: string, category_name: string|null, current_price: float|null, new_price: float|null, status: string}>, summary: array{changed: int, unchanged: int, missing: int, not_in_list: int}}
     */
    public function preview(PriceList $list): array
    {
        $items = $this->itemsWithTrashedProducts($list);
        $listedProductIds = $items->pluck('product_id')->all();
        $rows = [];
        $summary = ['changed' => 0, 'unchanged' => 0, 'missing' => 0, 'not_in_list' => 0];

        foreach ($items as $item) {
            $product = $item->product;
            $status = $this->statusFor($item, $product);

            if ($status === 'changed') {
                $summary['changed']++;
            } elseif ($status === 'unchanged') {
                $summary['unchanged']++;
            } else {
                $summary['missing']++;
            }

            $rows[] = [
                'product_id' => $item->product_id,
                'product_name' => $product === null ? 'Produit supprimé' : $product->name,
                'category_name' => $product?->category?->name,
                'current_price' => $product === null || $product->trashed() ? null : (float) $product->unit_price,
                'new_price' => (float) $item->unit_price,
                'status' => $status,
            ];
        }

        $productsNotInList = Product::query()
            ->with('category')
            ->whereNotIn('id', $listedProductIds)
            ->orderBy('name')
            ->get();

        foreach ($productsNotInList as $product) {
            $summary['not_in_list']++;
            $rows[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'category_name' => $product->category?->name,
                'current_price' => (float) $product->unit_price,
                'new_price' => null,
                'status' => 'not_in_list',
            ];
        }

        usort($rows, fn (array $a, array $b): int => strcmp($a['product_name'], $b['product_name']));

        return ['rows' => $rows, 'summary' => $summary];
    }

    /**
     * @return array{updated: int, skipped: int}
     */
    public function apply(PriceList $list): array
    {
        return DB::transaction(function () use ($list): array {
            $updated = 0;
            $skipped = 0;

            foreach ($this->itemsWithTrashedProducts($list) as $item) {
                $product = $item->product;

                if (! $product || $product->trashed()) {
                    $skipped++;

                    continue;
                }

                if ((float) $product->unit_price !== (float) $item->unit_price) {
                    $product->update(['unit_price' => $item->unit_price]);
                    $updated++;
                }
            }

            PriceList::query()
                ->where('is_active', true)
                ->whereKeyNot($list->id)
                ->get()
                ->each(fn (PriceList $active): bool => $active->update(['is_active' => false]));

            $list->update(['is_active' => true, 'last_applied_at' => now()]);

            return ['updated' => $updated, 'skipped' => $skipped];
        });
    }

    /**
     * @param  array<int, int>|null  $allProductIds
     * @return array{changed: int, missing: int, not_in_list: int}
     */
    public function drift(PriceList $list, ?array $allProductIds = null): array
    {
        $items = $list->relationLoaded('items') ? $list->items : $this->itemsWithTrashedProducts($list);
        $listedProductIds = $items->pluck('product_id')->all();

        $changed = 0;
        $missing = 0;

        foreach ($items as $item) {
            $status = $this->statusFor($item, $item->product);

            if ($status === 'changed') {
                $changed++;
            } elseif ($status === 'missing') {
                $missing++;
            }
        }

        $allProductIds ??= Product::query()->pluck('id')->map(fn ($id): int => (int) $id)->all();

        return [
            'changed' => $changed,
            'missing' => $missing,
            'not_in_list' => count(array_diff($allProductIds, $listedProductIds)),
        ];
    }

    /**
     * @return array{added: int, removed: int}
     */
    public function updateFromCatalog(PriceList $list): array
    {
        return DB::transaction(function () use ($list): array {
            $removed = 0;

            foreach ($this->itemsWithTrashedProducts($list) as $item) {
                if ($item->product === null || $item->product->trashed()) {
                    $item->delete();
                    $removed++;
                }
            }

            $existingProductIds = $list->items()->pluck('product_id')->all();
            $added = 0;

            Product::query()
                ->whereNotIn('id', $existingProductIds)
                ->get()
                ->each(function (Product $product) use ($list, &$added): void {
                    $list->items()->create([
                        'product_id' => $product->id,
                        'unit_price' => $product->unit_price,
                    ]);
                    $added++;
                });

            return ['added' => $added, 'removed' => $removed];
        });
    }

    /**
     * @return array{updated: int, skipped: int}
     */
    public function syncPrices(PriceList $list): array
    {
        return DB::transaction(function () use ($list): array {
            $updated = 0;
            $skipped = 0;

            foreach ($this->itemsWithTrashedProducts($list) as $item) {
                $product = $item->product;

                if (! $product || $product->trashed()) {
                    $skipped++;

                    continue;
                }

                if ((float) $item->unit_price !== (float) $product->unit_price) {
                    $item->update(['unit_price' => $product->unit_price]);
                    $updated++;
                }
            }

            return ['updated' => $updated, 'skipped' => $skipped];
        });
    }

    public function duplicate(PriceList $list): PriceList
    {
        return DB::transaction(function () use ($list): PriceList {
            $copy = PriceList::create([
                'name' => $this->uniqueName($list->name.' (copie)'),
                'notes' => $list->notes,
                'is_active' => false,
            ]);

            $copy->items()->createMany(
                $list->items()->get(['product_id', 'unit_price'])->map(fn (PriceListItem $item): array => [
                    'product_id' => $item->product_id,
                    'unit_price' => $item->unit_price,
                ])->all()
            );

            return $copy;
        });
    }

    /**
     * @return Collection<int, PriceListItem>
     */
    private function itemsWithTrashedProducts(PriceList $list): Collection
    {
        return $list->items()->with(['product' => fn ($query) => $query->withTrashed()])->get();
    }

    private function statusFor(PriceListItem $item, ?Product $product): string
    {
        if ($product === null || $product->trashed()) {
            return 'missing';
        }

        return (float) $product->unit_price === (float) $item->unit_price ? 'unchanged' : 'changed';
    }

    private function uniqueName(string $base): string
    {
        $name = $base;
        $suffix = 2;

        while (PriceList::query()->where('name', $name)->exists()) {
            $name = $base.' ('.$suffix.')';
            $suffix++;
        }

        return $name;
    }
}
