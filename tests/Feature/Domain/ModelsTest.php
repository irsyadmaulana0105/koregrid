<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Identity\Enums\PricingTier;
use App\Domain\Identity\Models\Company;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Ordering\Enums\PoStatus;
use App\Domain\Ordering\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('gives public models an integer key and a generated uuid used for route binding', function () {
    $company = Company::create(['code' => 'M-CMP', 'name' => 'Model Co', 'pricing_tier' => 'silver']);

    expect($company->getKey())->toBeInt()
        ->and(Str::isUuid($company->uuid))->toBeTrue()
        ->and($company->getRouteKeyName())->toBe('uuid');
});

it('casts the pricing tier to an enum', function () {
    $company = Company::create(['code' => 'M-CMP', 'name' => 'Model Co', 'pricing_tier' => 'gold'])->refresh();

    expect($company->pricing_tier)->toBe(PricingTier::Gold);
});

it('stores emails lower-cased and trimmed', function () {
    $user = User::factory()->create(['email' => '  Foo@Bar.COM ']);

    expect($user->email)->toBe('foo@bar.com');
});

it('hides secrets when a user is serialized', function () {
    $user = User::factory()->create();

    expect($user->toArray())->not->toHaveKeys(['password', 'remember_token']);
});

it('soft deletes companies instead of removing them', function () {
    $company = Company::create(['code' => 'M-CMP', 'name' => 'Model Co', 'pricing_tier' => 'bronze']);

    $company->delete();

    expect(Company::count())->toBe(0)->and(Company::withTrashed()->count())->toBe(1);
});

it('finds the price and MOQ for a given tier', function () {
    $product = Product::create(['sku' => 'M-SKU', 'name' => 'Model Product', 'unit' => 'pcs']);
    $product->tiers()->create(['tier' => 'gold', 'unit_price' => 85000, 'moq' => 50]);
    $product->tiers()->create(['tier' => 'bronze', 'unit_price' => 100000, 'moq' => 10]);

    $gold = $product->tierFor(PricingTier::Gold);

    expect($gold->moq)->toBe(50)
        ->and((float) $gold->unit_price)->toBe(85000.0)
        ->and($product->tierFor(PricingTier::Silver))->toBeNull()
        ->and($product->load('tiers')->tierFor(PricingTier::Bronze)->moq)->toBe(10);
});

it('computes available stock as on hand minus reserved', function () {
    $inventory = Inventory::create([
        'product_id' => Product::create(['sku' => 'M-SKU', 'name' => 'Model Product', 'unit' => 'pcs'])->id,
        'warehouse_id' => Warehouse::create(['code' => 'M-WH', 'name' => 'Model Warehouse'])->id,
        'qty_on_hand' => 10,
    ]);

    DB::table('inventories')->where('id', $inventory->id)->update(['qty_reserved' => 3]);

    expect($inventory->refresh()->available)->toBe(7);
});

it('starts purchase orders as draft and ignores workflow fields passed by mass assignment', function () {
    $company = Company::create(['code' => 'M-CMP', 'name' => 'Model Co', 'pricing_tier' => 'gold']);
    $buyer = User::factory()->create(['company_id' => $company->id]);

    $order = PurchaseOrder::create([
        'po_number' => 'M-PO-1',
        'company_id' => $company->id,
        'buyer_id' => $buyer->id,
        'pricing_tier' => 'gold',
        'status' => 'approved',        // tidak fillable
        'approved_by' => $buyer->id,   // tidak fillable
    ])->refresh();

    expect($order->status)->toBe(PoStatus::Draft)
        ->and($order->approved_by)->toBeNull()
        ->and($order->buyer->is($buyer))->toBeTrue()
        ->and($order->company->is($company))->toBeTrue();
});
