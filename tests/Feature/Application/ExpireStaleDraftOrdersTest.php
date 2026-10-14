<?php

use App\Application\Ordering\Actions\CreateDraftPurchaseOrder;
use App\Application\Ordering\Actions\ExpireStaleDraftOrders;
use App\Application\Ordering\DTOs\OrderLineData;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Enums\PricingTier;
use App\Domain\Ordering\Enums\PoStatus;
use App\Domain\Ordering\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;
use Tests\Support\Fixtures;

/** Membuat draft yang menahan 50 unit dari stok 100. */
function draftHoldingStock(): array
{
    $product = Fixtures::product();
    $warehouse = Fixtures::warehouse();
    $inventory = Fixtures::stock($product, $warehouse, 100);
    $order = app(CreateDraftPurchaseOrder::class)(
        Fixtures::buyer(Fixtures::company(PricingTier::Gold)),
        [new OrderLineData($product->id, $warehouse->id, 50)],
    );

    return [$order, $inventory];
}

function expireReservationOf(PurchaseOrder $order): void
{
    DB::table('purchase_orders')->where('id', $order->id)->update(['reservation_expires_at' => now()->subMinute()]);
}

it('cancels an expired draft and releases its stock', function () {
    [$order, $inventory] = draftHoldingStock();
    expireReservationOf($order);

    $count = app(ExpireStaleDraftOrders::class)();

    $audit = AuditLog::where('event', 'status_changed')->sole();
    expect($count)->toBe(1)
        ->and($order->refresh()->status)->toBe(PoStatus::Cancelled)
        ->and($inventory->refresh()->qty_reserved)->toBe(0)
        ->and($audit->new_values['reason'])->toBe('reservation_expired')
        ->and($audit->user_id)->toBeNull();
});

it('leaves drafts that have not expired yet', function () {
    [$order, $inventory] = draftHoldingStock();

    expect(app(ExpireStaleDraftOrders::class)())->toBe(0)
        ->and($order->refresh()->status)->toBe(PoStatus::Draft)
        ->and($inventory->refresh()->qty_reserved)->toBe(50);
});

it('ignores orders that are no longer drafts, even with an old expiry', function () {
    [$order, $inventory] = draftHoldingStock();
    expireReservationOf($order);
    DB::table('purchase_orders')->where('id', $order->id)->update(['status' => PoStatus::PendingApproval->value]);

    expect(app(ExpireStaleDraftOrders::class)())->toBe(0)
        ->and($order->refresh()->status)->toBe(PoStatus::PendingApproval)
        ->and($inventory->refresh()->qty_reserved)->toBe(50);
});

it('is safe to run twice in a row', function () {
    [$order] = draftHoldingStock();
    expireReservationOf($order);

    expect(app(ExpireStaleDraftOrders::class)())->toBe(1)
        ->and(app(ExpireStaleDraftOrders::class)())->toBe(0);
});

it('can be run as an artisan command', function () {
    [$order] = draftHoldingStock();
    expireReservationOf($order);

    $this->artisan('orders:expire-drafts')
        ->expectsOutput('1 draft PO dibatalkan.')
        ->assertSuccessful();
});
