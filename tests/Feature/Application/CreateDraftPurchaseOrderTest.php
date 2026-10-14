<?php

use App\Application\Ordering\Actions\CreateDraftPurchaseOrder;
use App\Application\Ordering\DTOs\OrderLineData;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Catalog\Exceptions\ProductNotAvailableException;
use App\Domain\Catalog\Exceptions\TierPriceNotFoundException;
use App\Domain\Identity\Enums\PricingTier;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Ordering\Enums\PoStatus;
use App\Domain\Ordering\Exceptions\InvalidPurchaseOrderException;
use App\Domain\Ordering\Exceptions\MoqNotMetException;
use App\Domain\Ordering\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures;

function createDraftOrder(User $buyer, OrderLineData ...$lines): PurchaseOrder
{
    return app(CreateDraftPurchaseOrder::class)($buyer, array_values($lines));
}

it('creates a draft with tier price, tax, reserved stock, expiry and an audit entry', function () {
    $product = Fixtures::product();
    $warehouse = Fixtures::warehouse();
    $inventory = Fixtures::stock($product, $warehouse, 200);
    $buyer = Fixtures::buyer(Fixtures::company(PricingTier::Gold));

    $order = createDraftOrder($buyer, new OrderLineData($product->id, $warehouse->id, 50));

    $item = $order->items->sole();
    expect($order->status)->toBe(PoStatus::Draft)
        ->and($order->pricing_tier)->toBe(PricingTier::Gold)
        ->and($order->buyer_id)->toBe($buyer->id)
        ->and($order->company_id)->toBe($buyer->company_id)
        ->and($order->po_number)->toMatch('/^PO-\d{4}-\d{6}$/')
        ->and($item->unit_price)->toBe('85000.00')
        ->and($item->moq_applied)->toBe(50)
        ->and($item->line_total)->toBe('4250000.00')
        ->and($order->subtotal)->toBe('4250000.00')
        ->and($order->tax_amount)->toBe('467500.00')
        ->and($order->total_amount)->toBe('4717500.00')
        ->and($inventory->refresh()->qty_reserved)->toBe(50)
        ->and($order->reservation_expires_at->between(now()->addHours(23), now()->addHours(25)))->toBeTrue();

    $audit = AuditLog::where('event', 'created')->sole();
    expect($audit->auditable_id)->toBe($order->id)
        ->and($audit->new_values['po_number'])->toBe($order->po_number);
});

it('prices each order by the tier of the buyer company', function (PricingTier $tier, string $price, int $quantity) {
    $product = Fixtures::product();
    $warehouse = Fixtures::warehouse();
    Fixtures::stock($product, $warehouse, 200);

    $order = createDraftOrder(
        Fixtures::buyer(Fixtures::company($tier)),
        new OrderLineData($product->id, $warehouse->id, $quantity),
    );

    expect($order->items->sole()->unit_price)->toBe($price)->and($order->pricing_tier)->toBe($tier);
})->with([
    'bronze' => [PricingTier::Bronze, '100000.00', 10],
    'silver' => [PricingTier::Silver, '92000.00', 25],
    'gold' => [PricingTier::Gold, '85000.00', 50],
]);

it('checks the MOQ against the total quantity across warehouses', function () {
    $product = Fixtures::product();
    $first = Fixtures::warehouse('F-WH1');
    $second = Fixtures::warehouse('F-WH2');
    Fixtures::stock($product, $first, 100);
    Fixtures::stock($product, $second, 100);

    $order = createDraftOrder(
        Fixtures::buyer(Fixtures::company(PricingTier::Gold)),
        new OrderLineData($product->id, $first->id, 30),
        new OrderLineData($product->id, $second->id, 20),
    );

    expect($order->items)->toHaveCount(2)
        ->and(Inventory::where('warehouse_id', $first->id)->value('qty_reserved'))->toBe(30)
        ->and(Inventory::where('warehouse_id', $second->id)->value('qty_reserved'))->toBe(20);
});

it('merges duplicate lines for the same product and warehouse', function () {
    $product = Fixtures::product();
    $warehouse = Fixtures::warehouse();
    Fixtures::stock($product, $warehouse, 100);

    $order = createDraftOrder(
        Fixtures::buyer(Fixtures::company(PricingTier::Gold)),
        new OrderLineData($product->id, $warehouse->id, 30),
        new OrderLineData($product->id, $warehouse->id, 20),
    );

    expect($order->items->sole()->quantity)->toBe(50);
});

it('rejects an order below the MOQ and leaves nothing behind', function () {
    $product = Fixtures::product();
    $warehouse = Fixtures::warehouse();
    $inventory = Fixtures::stock($product, $warehouse, 100);

    expect(fn () => createDraftOrder(
        Fixtures::buyer(Fixtures::company(PricingTier::Gold)),
        new OrderLineData($product->id, $warehouse->id, 49),
    ))->toThrow(MoqNotMetException::class);

    expect(PurchaseOrder::count())->toBe(0)->and($inventory->refresh()->qty_reserved)->toBe(0);
});

it('rolls back every reservation when one line lacks stock', function () {
    $ample = Fixtures::product('F-AMPLE');
    $scarce = Fixtures::product('F-SCARCE');
    $warehouse = Fixtures::warehouse();
    $ampleStock = Fixtures::stock($ample, $warehouse, 100);
    $scarceStock = Fixtures::stock($scarce, $warehouse, 10);

    expect(fn () => createDraftOrder(
        Fixtures::buyer(Fixtures::company(PricingTier::Bronze)),
        new OrderLineData($ample->id, $warehouse->id, 20),
        new OrderLineData($scarce->id, $warehouse->id, 20),
    ))->toThrow(InsufficientStockException::class);

    expect(PurchaseOrder::count())->toBe(0)
        ->and($ampleStock->refresh()->qty_reserved)->toBe(0)
        ->and($scarceStock->refresh()->qty_reserved)->toBe(0)
        ->and(AuditLog::count())->toBe(0);
});

it('never lets parallel demand exceed the stock', function () {
    $product = Fixtures::product();
    $warehouse = Fixtures::warehouse();
    $inventory = Fixtures::stock($product, $warehouse, 100);
    $buyer = Fixtures::buyer(Fixtures::company(PricingTier::Gold));
    $line = new OrderLineData($product->id, $warehouse->id, 50);

    createDraftOrder($buyer, $line);
    createDraftOrder($buyer, $line);

    expect(fn () => createDraftOrder($buyer, $line))->toThrow(InsufficientStockException::class);

    $inventory->refresh();
    expect($inventory->qty_reserved)->toBe(100)
        ->and($inventory->available)->toBe(0)
        ->and(PurchaseOrder::count())->toBe(2);
});

it('keeps the price snapshot when prices or the company tier change later', function () {
    $product = Fixtures::product();
    $warehouse = Fixtures::warehouse();
    Fixtures::stock($product, $warehouse, 100);
    $company = Fixtures::company(PricingTier::Gold);

    $order = createDraftOrder(Fixtures::buyer($company), new OrderLineData($product->id, $warehouse->id, 50));

    DB::table('product_tiers')->where('product_id', $product->id)->update(['unit_price' => 1]);
    $company->forceFill(['pricing_tier' => PricingTier::Bronze])->save();

    $order->refresh()->load('items');
    expect($order->items->sole()->unit_price)->toBe('85000.00')
        ->and($order->pricing_tier)->toBe(PricingTier::Gold)
        ->and($order->total_amount)->toBe('4717500.00');
});

it('issues unique, increasing PO numbers', function () {
    $product = Fixtures::product();
    $warehouse = Fixtures::warehouse();
    Fixtures::stock($product, $warehouse, 500);
    $buyer = Fixtures::buyer(Fixtures::company(PricingTier::Bronze));
    $line = new OrderLineData($product->id, $warehouse->id, 10);

    $numbers = [
        createDraftOrder($buyer, $line)->po_number,
        createDraftOrder($buyer, $line)->po_number,
        createDraftOrder($buyer, $line)->po_number,
    ];

    expect(array_unique($numbers))->toHaveCount(3)
        ->and($numbers)->toBe(collect($numbers)->sort()->values()->all());
});

it('rejects orders that cannot be fulfilled', function (Closure $scenario, string $exception) {
    expect($scenario)->toThrow($exception);
})->with([
    'inactive product' => [function () {
        $product = Fixtures::product();
        $warehouse = Fixtures::warehouse();
        Fixtures::stock($product, $warehouse, 100);
        $product->forceFill(['is_active' => false])->save();

        return createDraftOrder(Fixtures::buyer(), new OrderLineData($product->id, $warehouse->id, 50));
    }, ProductNotAvailableException::class],
    'unknown product' => [function () {
        $warehouse = Fixtures::warehouse();

        return createDraftOrder(Fixtures::buyer(), new OrderLineData(999999, $warehouse->id, 50));
    }, ProductNotAvailableException::class],
    'no price for the tier' => [function () {
        $product = Fixtures::product('F-BRONZE-ONLY', ['bronze' => ['100000', 10]]);
        $warehouse = Fixtures::warehouse();
        Fixtures::stock($product, $warehouse, 100);

        return createDraftOrder(Fixtures::buyer(Fixtures::company(PricingTier::Gold)), new OrderLineData($product->id, $warehouse->id, 50));
    }, TierPriceNotFoundException::class],
    'inactive warehouse' => [function () {
        $product = Fixtures::product();
        $warehouse = Fixtures::warehouse();
        Fixtures::stock($product, $warehouse, 100);
        $warehouse->forceFill(['is_active' => false])->save();

        return createDraftOrder(Fixtures::buyer(), new OrderLineData($product->id, $warehouse->id, 50));
    }, InvalidPurchaseOrderException::class],
    'empty order' => [fn () => createDraftOrder(Fixtures::buyer()), InvalidPurchaseOrderException::class],
    'zero quantity' => [function () {
        $product = Fixtures::product();
        $warehouse = Fixtures::warehouse();

        return createDraftOrder(Fixtures::buyer(), new OrderLineData($product->id, $warehouse->id, 0));
    }, InvalidPurchaseOrderException::class],
    'buyer without company' => [function () {
        $product = Fixtures::product();
        $warehouse = Fixtures::warehouse();
        Fixtures::stock($product, $warehouse, 100);

        return createDraftOrder(User::factory()->create(), new OrderLineData($product->id, $warehouse->id, 50));
    }, InvalidPurchaseOrderException::class],
    'inactive buyer' => [function () {
        $product = Fixtures::product();
        $warehouse = Fixtures::warehouse();
        Fixtures::stock($product, $warehouse, 100);
        $buyer = Fixtures::buyer();
        $buyer->forceFill(['is_active' => false])->save();

        return createDraftOrder($buyer, new OrderLineData($product->id, $warehouse->id, 50));
    }, InvalidPurchaseOrderException::class],
    'inactive company' => [function () {
        $product = Fixtures::product();
        $warehouse = Fixtures::warehouse();
        Fixtures::stock($product, $warehouse, 100);
        $company = Fixtures::company();
        $company->forceFill(['is_active' => false])->save();

        return createDraftOrder(Fixtures::buyer($company), new OrderLineData($product->id, $warehouse->id, 50));
    }, InvalidPurchaseOrderException::class],
]);
