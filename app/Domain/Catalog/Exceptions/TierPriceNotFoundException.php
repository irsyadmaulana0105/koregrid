<?php

namespace App\Domain\Catalog\Exceptions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Identity\Enums\PricingTier;
use DomainException;

final class TierPriceNotFoundException extends DomainException
{
    public static function for(Product $product, PricingTier $tier): self
    {
        return new self("Produk {$product->sku} belum memiliki harga untuk tier {$tier->value}.");
    }
}
