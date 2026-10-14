<?php

namespace App\Application\Catalog\Actions;

use App\Domain\Catalog\Exceptions\ProductNotAvailableException;
use App\Domain\Catalog\Exceptions\TierPriceNotFoundException;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductTier;
use App\Domain\Identity\Enums\PricingTier;

/**
 * Menentukan harga dan MOQ sebuah produk untuk tier tertentu.
 */
final class ResolveTierPrice
{
    public function __invoke(Product $product, PricingTier $tier): ProductTier
    {
        if (! $product->is_active) {
            throw ProductNotAvailableException::inactive($product);
        }

        return $product->tierFor($tier) ?? throw TierPriceNotFoundException::for($product, $tier);
    }
}
