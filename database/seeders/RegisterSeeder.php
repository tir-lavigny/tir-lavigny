<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class RegisterSeeder extends Seeder
{
    public function run(): void
    {
        $shootingRange = Category::updateOrCreate(
            ['name' => 'Tir'],
            ['is_restricted' => false]
        );

        $bar = Category::updateOrCreate(
            ['name' => 'Bar'],
            ['is_restricted' => false]
        );

        $correction = Category::updateOrCreate(
            ['name' => 'Correction'],
            ['is_restricted' => true]
        );

        $this->createShootingRangeProducts($shootingRange);
        $this->createBarProducts($bar);
        $this->createCorrectionProducts($correction);
    }

    private function createShootingRangeProducts(Category $category): void
    {
        $cartridge = Product::updateOrCreate(
            ['name' => 'Cartouche'],
            ['unit_price' => 0.50, 'category_id' => $category->id]
        );

        $bundles = [
            ['name' => 'Tir du jeudi', 'unit_price' => 10.00, 'quantity' => 10],
            ['name' => 'Tirs militaires', 'unit_price' => 0.00, 'quantity' => 20],
            ['name' => 'Tir Fédéral en Campagne', 'unit_price' => 0.00, 'quantity' => 18],
            ['name' => 'Ouverture', 'unit_price' => 10.00, 'quantity' => 12],
            ['name' => 'Clôture', 'unit_price' => 5.00, 'quantity' => 5],
        ];

        foreach ($bundles as $bundle) {
            $product = Product::updateOrCreate(
                ['name' => $bundle['name']],
                ['unit_price' => $bundle['unit_price'], 'category_id' => $category->id]
            );

            $product->childProducts()->syncWithoutDetaching([
                $cartridge->id => ['quantity' => $bundle['quantity']],
            ]);
        }
    }

    private function createBarProducts(Category $category): void
    {
        $products = [
            ['name' => 'Vin rouge 0.75l', 'unit_price' => 10.00],
            ['name' => 'Vin rouge 0.5l', 'unit_price' => 7.00],
            ['name' => 'Vin blanc 0.75l', 'unit_price' => 10.00],
            ['name' => 'Vin blanc 0.5l', 'unit_price' => 7.00],
            ['name' => 'Vin rosé 0.75l', 'unit_price' => 10.00],
            ['name' => 'Vin rosé 0.5l', 'unit_price' => 7.00],
            ['name' => 'Café/Thé', 'unit_price' => 1.50],
            ['name' => 'Minérale', 'unit_price' => 3.00],
            ['name' => 'Bière', 'unit_price' => 3.00],
            ['name' => 'Bière s. alcool', 'unit_price' => 3.00],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['name' => $product['name']],
                ['unit_price' => $product['unit_price'], 'category_id' => $category->id]
            );
        }
    }

    private function createCorrectionProducts(Category $category): void
    {
        Product::updateOrCreate(
            ['name' => 'Correction'],
            ['unit_price' => 0, 'allows_custom_price' => true, 'category_id' => $category->id]
        );
    }
}
