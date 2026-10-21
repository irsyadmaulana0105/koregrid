<?php

namespace App\Application\Catalog\Queries;

use App\Domain\Catalog\Models\Product;
use App\Domain\Identity\Enums\PricingTier;
use App\Domain\Inventory\Models\Inventory;

/**
 * Katalog untuk satu tier: produk aktif beserta harga, MOQ, dan stok tersedia per gudang aktif.
 * Tier null (user tanpa perusahaan) berarti harga dan MOQ tidak ditampilkan.
 */
final class CatalogQuery
{
    /**
     * @return list<array{uuid: string, sku: string, name: string, unit: string, price: string|null, moq: int|null, stock: list<array{warehouse_code: string, warehouse_name: string, available: int}>}>
     */
    public function __invoke(?PricingTier $tier): array
    {
        $products = Product::query()
            ->where('is_active', true)
            ->with(['tiers', 'inventories.warehouse'])
            ->orderBy('name')
            ->get();

        $rows = [];

        foreach ($products as $product) {
            $price = $tier === null ? null : $product->tierFor($tier);

            $stock = [];
            foreach ($product->inventories->sortBy(fn (Inventory $inventory) => $inventory->warehouse->code) as $inventory) {
                $warehouse = $inventory->warehouse;

                if (! $warehouse->is_active) {
                    continue;
                }

                $stock[] = [
                    'warehouse_code' => (string) $warehouse->code,
                    'warehouse_name' => (string) $warehouse->name,
                    'available' => max(0, (int) $inventory->qty_on_hand - (int) $inventory->qty_reserved),
                ];
            }

            $rows[] = [
                'uuid' => (string) $product->uuid,
                'sku' => (string) $product->sku,
                'name' => (string) $product->name,
                'unit' => (string) $product->unit,
                'price' => $price === null ? null : (string) $price->unit_price,
                'moq' => $price === null ? null : (int) $price->moq,
                'stock' => $stock,
            ];
        }

        return $rows;
    }
}
