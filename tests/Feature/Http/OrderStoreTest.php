<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Enums\PricingTier;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Ordering\Models\PurchaseOrder;
use Illuminate\Support\Str;
use Tests\Support\Fixtures;

beforeEach(function () {
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);
});

/** Pembeli gold dengan satu produk (harga gold 85.000, MOQ 50) dan stok 200 di satu gudang. */
function orderFlowSetup(): array
{
    $company = Fixtures::company(PricingTier::Gold, 'F-CMP-1');
    $buyer = Fixtures::user(RoleName::Buyer, $company);
    $warehouse = Fixtures::warehouse('F-WH-1');
    $product = Fixtures::product('F-SKU-1');
    Fixtures::stock($product, $warehouse, 200);

    return [$buyer, $product, $warehouse];
}

function orderFlowLine(string $product, string $warehouse, int $quantity): array
{
    return ['product' => $product, 'warehouse' => $warehouse, 'quantity' => $quantity];
}

it('creates a draft priced by the buyer tier and ignores any client price', function () {
    [$buyer, $product, $warehouse] = orderFlowSetup();

    $this->actingAs($buyer)->from('/catalog')->post('/orders', [
        'lines' => [orderFlowLine($product->uuid, $warehouse->code, 60) + ['unit_price' => '1']],
    ])->assertRedirectContains('/orders/')->assertSessionHas('success');

    $order = PurchaseOrder::query()->sole();

    expect($order->status->value)->toBe('draft')
        ->and($order->company_id)->toEqual($buyer->company_id)
        ->and($order->subtotal)->toBe('5100000.00')
        ->and($order->total_amount)->toBe('5661000.00')
        ->and(Inventory::query()->sole()->qty_reserved)->toBe(60)
        ->and(AuditLog::query()->where('event', 'created')->count())->toBe(1);
});

it('reports a MOQ shortfall as a message and creates nothing', function () {
    [$buyer, $product, $warehouse] = orderFlowSetup();

    $this->actingAs($buyer)->from('/catalog')->post('/orders', [
        'lines' => [orderFlowLine($product->uuid, $warehouse->code, 10)],
    ])->assertRedirect('/catalog')->assertSessionHasErrors('cart');

    expect(PurchaseOrder::query()->count())->toBe(0)
        ->and(Inventory::query()->sole()->qty_reserved)->toBe(0);
});

it('reports insufficient stock as a message and creates nothing', function () {
    [$buyer, $product, $warehouse] = orderFlowSetup();

    $this->actingAs($buyer)->from('/catalog')->post('/orders', [
        'lines' => [orderFlowLine($product->uuid, $warehouse->code, 500)],
    ])->assertRedirect('/catalog')->assertSessionHasErrors('cart');

    expect(PurchaseOrder::query()->count())->toBe(0)
        ->and(Inventory::query()->sole()->qty_reserved)->toBe(0);
});

it('rejects a product uuid that does not exist', function () {
    [$buyer, , $warehouse] = orderFlowSetup();

    $this->actingAs($buyer)->from('/catalog')->post('/orders', [
        'lines' => [orderFlowLine((string) Str::uuid(), $warehouse->code, 60)],
    ])->assertSessionHasErrors('cart');

    expect(PurchaseOrder::query()->count())->toBe(0);
});

it('rejects a warehouse code that does not exist', function () {
    [$buyer, $product] = orderFlowSetup();

    $this->actingAs($buyer)->from('/catalog')->post('/orders', [
        'lines' => [orderFlowLine($product->uuid, 'TIDAK-ADA', 60)],
    ])->assertSessionHasErrors('cart');

    expect(PurchaseOrder::query()->count())->toBe(0);
});

it('validates the shape of the request', function () {
    [$buyer] = orderFlowSetup();

    $this->actingAs($buyer)->post('/orders', ['lines' => []])
        ->assertSessionHasErrors('lines');

    $this->actingAs($buyer)->post('/orders', [
        'lines' => [orderFlowLine('bukan-uuid', 'F-WH-1', 0)],
    ])->assertSessionHasErrors(['lines.0.product', 'lines.0.quantity']);

    $this->actingAs($buyer)->post('/orders', [
        'lines' => array_fill(0, 51, orderFlowLine((string) Str::uuid(), 'F-WH-1', 1)),
    ])->assertSessionHasErrors('lines');
});

it('forbids users who may not create orders', function () {
    [, $product, $warehouse] = orderFlowSetup();
    $payload = ['lines' => [orderFlowLine($product->uuid, $warehouse->code, 60)]];

    $this->actingAs(Fixtures::user(RoleName::Finance))->post('/orders', $payload)->assertForbidden();
    $this->actingAs(Fixtures::buyer(Fixtures::company(PricingTier::Gold, 'F-CMP-9')))->post('/orders', $payload)->assertForbidden();

    expect(PurchaseOrder::query()->count())->toBe(0);
});

it('sends guests to the login page', function () {
    $this->post('/orders', ['lines' => []])->assertRedirect('/login');
});

it('rate limits order creation per user', function () {
    [$buyer] = orderFlowSetup();

    foreach (range(1, 30) as $ignored) {
        $this->actingAs($buyer)->post('/orders', ['lines' => []])->assertStatus(302);
    }

    $this->actingAs($buyer)->post('/orders', ['lines' => []])->assertStatus(429);
});
