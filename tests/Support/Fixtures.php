<?php

namespace Tests\Support;

use App\Domain\Catalog\Models\Product;
use App\Domain\Identity\Enums\PricingTier;
use App\Domain\Identity\Models\Company;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\Warehouse;

/**
 * Pembuat data uji. Kode dan SKU harus unik per test, jadi beri nilai berbeda untuk entitas kedua.
 *
 * is_active diisi eksplisit: nilai bawaan `true` ada di database, bukan di objek model yang baru dibuat,
 * sehingga tanpa ini kode yang memeriksa `$model->is_active` melihat null dan menolak entitasnya.
 */
final class Fixtures
{
    public static function company(PricingTier $tier = PricingTier::Gold, string $code = 'F-CMP'): Company
    {
        return Company::create(['code' => $code, 'name' => "Company {$code}", 'pricing_tier' => $tier, 'is_active' => true]);
    }

    public static function buyer(?Company $company = null): User
    {
        $company ??= self::company();

        return User::factory()->create(['company_id' => $company->id]);
    }

    public static function warehouse(string $code = 'F-WH'): Warehouse
    {
        return Warehouse::create(['code' => $code, 'name' => "Warehouse {$code}", 'is_active' => true]);
    }

    /**
     * @param  array<string, array{0: string, 1: int}>|null  $tiers  tier => [harga, MOQ]
     */
    public static function product(string $sku = 'F-SKU', ?array $tiers = null): Product
    {
        $product = Product::create(['sku' => $sku, 'name' => "Product {$sku}", 'unit' => 'pcs', 'is_active' => true]);

        $tiers ??= [
            'bronze' => ['100000', 10],
            'silver' => ['92000', 25],
            'gold' => ['85000', 50],
        ];

        foreach ($tiers as $tier => [$price, $moq]) {
            $product->tiers()->create(['tier' => $tier, 'unit_price' => $price, 'moq' => $moq]);
        }

        return $product;
    }

    public static function stock(Product $product, Warehouse $warehouse, int $onHand): Inventory
    {
        return Inventory::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'qty_on_hand' => $onHand,
        ]);
    }
}
