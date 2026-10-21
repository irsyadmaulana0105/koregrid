<?php

use App\Domain\Identity\Enums\PricingTier;
use App\Domain\Identity\Enums\RoleName;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Fixtures;
use Tests\Support\OrderScenario;

beforeEach(function () {
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);
});

it('prices the catalog by the tier of the buyer company', function (PricingTier $tier, string $price, int $moq) {
    $company = Fixtures::company($tier, 'F-CMP-1');
    $buyer = Fixtures::user(RoleName::Buyer, $company);
    $warehouse = Fixtures::warehouse('F-WH-1');
    $product = Fixtures::product('F-SKU-1');
    Fixtures::stock($product, $warehouse, 200);

    $this->actingAs($buyer)->get('/catalog')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Catalog')
            ->where('products.0.sku', 'F-SKU-1')
            ->where('products.0.price', $price)
            ->where('products.0.moq', $moq)
            ->where('products.0.stock.0.warehouse_code', 'F-WH-1')
            ->where('products.0.stock.0.available', 200));
})->with([
    'gold' => [PricingTier::Gold, '85000.00', 50],
    'silver' => [PricingTier::Silver, '92000.00', 25],
    'bronze' => [PricingTier::Bronze, '100000.00', 10],
]);

it('subtracts reserved stock from the available quantity', function () {
    $scenario = OrderScenario::draft(quantity: 50, onHand: 200);

    $this->actingAs($scenario->buyer)->get('/catalog')
        ->assertInertia(fn (Assert $page) => $page->where('products.0.stock.0.available', 150));
});

it('shows stock but no price to a user without a company', function () {
    $warehouse = Fixtures::warehouse('F-WH-1');
    $product = Fixtures::product('F-SKU-1');
    Fixtures::stock($product, $warehouse, 80);

    $this->actingAs(Fixtures::user(RoleName::Finance))->get('/catalog')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.0.price', null)
            ->where('products.0.moq', null)
            ->where('products.0.stock.0.available', 80));
});

it('hides inactive products', function () {
    $product = Fixtures::product('F-SKU-1');
    $product->update(['is_active' => false]);

    $this->actingAs(Fixtures::user(RoleName::Finance))->get('/catalog')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('products', 0));
});

it('forbids a user without the product.view permission', function () {
    $this->actingAs(Fixtures::buyer(Fixtures::company(PricingTier::Gold, 'F-CMP-9')))
        ->get('/catalog')
        ->assertForbidden();
});

it('sends guests to the login page', function () {
    $this->get('/catalog')->assertRedirect('/login');
});
