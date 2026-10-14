<?php

use App\Application\Catalog\Actions\ResolveTierPrice;
use App\Domain\Catalog\Exceptions\ProductNotAvailableException;
use App\Domain\Catalog\Exceptions\TierPriceNotFoundException;
use App\Domain\Identity\Enums\PricingTier;
use Tests\Support\Fixtures;

it('returns the price and MOQ for the requested tier', function (PricingTier $tier, string $price, int $moq) {
    $row = app(ResolveTierPrice::class)(Fixtures::product(), $tier);

    expect((string) $row->unit_price)->toBe($price)->and($row->moq)->toBe($moq);
})->with([
    'bronze' => [PricingTier::Bronze, '100000.00', 10],
    'silver' => [PricingTier::Silver, '92000.00', 25],
    'gold' => [PricingTier::Gold, '85000.00', 50],
]);

it('refuses inactive products', function () {
    $product = Fixtures::product();
    $product->forceFill(['is_active' => false])->save();

    expect(fn () => app(ResolveTierPrice::class)($product, PricingTier::Gold))
        ->toThrow(ProductNotAvailableException::class);
});

it('fails when the product has no price for the tier', function () {
    $product = Fixtures::product('ONLY-BRONZE', ['bronze' => ['100000', 10]]);

    expect(fn () => app(ResolveTierPrice::class)($product, PricingTier::Gold))
        ->toThrow(TierPriceNotFoundException::class);
});
