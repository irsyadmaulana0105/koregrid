<?php

use App\Application\Ordering\Actions\ApprovePurchaseOrder;
use App\Application\Ordering\Actions\CancelPurchaseOrder;
use App\Application\Ordering\Actions\ExpireStaleDraftOrders;
use App\Application\Ordering\Actions\RejectPurchaseOrder;
use App\Application\Ordering\Actions\ShipPurchaseOrder;
use App\Application\Ordering\Actions\StartPackingPurchaseOrder;
use App\Application\Ordering\Actions\SubmitPurchaseOrder;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Ordering\Enums\PoStatus;
use App\Domain\Ordering\Exceptions\InvalidPurchaseOrderException;
use App\Domain\Ordering\Exceptions\InvalidStatusTransitionException;
use Illuminate\Support\Facades\DB;
use Tests\Support\OrderScenario;

it('walks a purchase order through the whole lifecycle', function () {
    $s = OrderScenario::draft(quantity: 50, onHand: 200);
    expect($s->order->status)->toBe(PoStatus::Draft)
        ->and($s->inventory->qty_reserved)->toBe(50);

    $s->submit();
    expect($s->order->status)->toBe(PoStatus::PendingApproval)
        ->and($s->order->submitted_at)->not->toBeNull()
        ->and($s->order->reservation_expires_at)->toBeNull()
        ->and($s->inventory->qty_reserved)->toBe(50);

    $s->approve();
    expect($s->order->status)->toBe(PoStatus::Approved)
        ->and($s->order->approved_by)->toBe($s->finance->id)
        ->and($s->order->approved_at)->not->toBeNull();

    $s->startPacking();
    expect($s->order->status)->toBe(PoStatus::Packing)
        ->and($s->order->packed_by)->toBe($s->staff->id);

    $s->ship('TRK-12345');
    expect($s->order->status)->toBe(PoStatus::Shipped)
        ->and($s->order->tracking_number)->toBe('TRK-12345')
        ->and($s->order->shipped_at)->not->toBeNull()
        ->and($s->inventory->qty_on_hand)->toBe(150)
        ->and($s->inventory->qty_reserved)->toBe(0);

    $s->complete();
    expect($s->order->status)->toBe(PoStatus::Completed)
        ->and($s->order->completed_at)->not->toBeNull()
        ->and($s->order->invoice_number)->toMatch('/^INV-\d{4}-\d{6}$/')
        ->and($s->order->invoice_issued_at)->not->toBeNull()
        ->and($s->inventory->qty_on_hand)->toBe(150);
});

it('attributes every audit entry to the actor who performed it', function () {
    $s = OrderScenario::draft()->advanceTo(PoStatus::Completed);

    $entries = AuditLog::where('auditable_id', $s->order->id)->orderBy('id')->get();

    expect($entries->pluck('event')->all())->toBe([
        'created', 'status_changed', 'status_changed', 'status_changed', 'status_changed', 'status_changed',
    ])->and($entries->pluck('user_id')->all())->toBe([
        $s->buyer->id, $s->buyer->id, $s->finance->id, $s->staff->id, $s->staff->id, $s->buyer->id,
    ])->and($entries->last()->new_values['invoice_number'])->toBe($s->order->invoice_number);
});

it('issues unique and increasing invoice numbers', function () {
    $first = OrderScenario::draft(tag: 'A')->advanceTo(PoStatus::Completed);
    $second = OrderScenario::draft(tag: 'B')->advanceTo(PoStatus::Completed);

    expect($second->order->invoice_number)->not->toBe($first->order->invoice_number)
        ->and($second->order->invoice_number > $first->order->invoice_number)->toBeTrue();
});

it('releases the reserved stock when finance rejects an order, and keeps the reason', function () {
    $s = OrderScenario::draft()->submit();

    app(RejectPurchaseOrder::class)($s->finance, $s->order, '  Limit kredit terlampaui  ');
    $s->order->refresh();
    $s->inventory->refresh();

    $audit = AuditLog::where('auditable_id', $s->order->id)->where('event', 'status_changed')->latest('id')->first();
    expect($s->order->status)->toBe(PoStatus::Rejected)
        ->and($s->order->rejection_reason)->toBe('Limit kredit terlampaui')
        ->and($s->order->rejected_at)->not->toBeNull()
        ->and($s->inventory->qty_reserved)->toBe(0)
        ->and($audit->new_values['reason'])->toBe('Limit kredit terlampaui')
        ->and($audit->user_id)->toBe($s->finance->id);
});

it('releases the reserved stock when the buyer cancels', function (PoStatus $state) {
    $s = OrderScenario::draft()->advanceTo($state);

    app(CancelPurchaseOrder::class)($s->buyer, $s->order, 'salah pesan');
    $s->order->refresh();
    $s->inventory->refresh();

    expect($s->order->status)->toBe(PoStatus::Cancelled)->and($s->inventory->qty_reserved)->toBe(0);
})->with([PoStatus::Draft, PoStatus::PendingApproval]);

it('cannot cancel once the order is approved', function () {
    $s = OrderScenario::draft()->advanceTo(PoStatus::Approved);

    expect(fn () => app(CancelPurchaseOrder::class)($s->buyer, $s->order))->toThrow(InvalidStatusTransitionException::class);

    expect($s->order->refresh()->status)->toBe(PoStatus::Approved)
        ->and($s->inventory->refresh()->qty_reserved)->toBe(50);
});

it('requires a reason to reject and changes nothing without one', function (string $reason) {
    $s = OrderScenario::draft()->submit();

    expect(fn () => app(RejectPurchaseOrder::class)($s->finance, $s->order, $reason))
        ->toThrow(InvalidPurchaseOrderException::class);

    expect($s->order->refresh()->status)->toBe(PoStatus::PendingApproval)
        ->and($s->inventory->refresh()->qty_reserved)->toBe(50);
})->with(['empty' => [''], 'only spaces' => ['   ']]);

it('requires a valid tracking number to ship and does not move stock without one', function (string $tracking) {
    $s = OrderScenario::draft()->advanceTo(PoStatus::Packing);

    expect(fn () => app(ShipPurchaseOrder::class)($s->staff, $s->order, $tracking))
        ->toThrow(InvalidPurchaseOrderException::class);

    expect($s->order->refresh()->status)->toBe(PoStatus::Packing)
        ->and($s->inventory->refresh()->qty_on_hand)->toBe(200)
        ->and($s->inventory->qty_reserved)->toBe(50);
})->with(['empty' => [''], 'too long' => [str_repeat('x', 101)]]);

it('refuses to skip steps or repeat a step', function (Closure $attempt) {
    $s = OrderScenario::draft();

    expect(fn () => $attempt($s))->toThrow(InvalidStatusTransitionException::class);
})->with([
    'approve a draft' => [fn (OrderScenario $s) => app(ApprovePurchaseOrder::class)($s->finance, $s->order)],
    'pack a submitted order' => [function (OrderScenario $s) {
        $s->submit();
        app(StartPackingPurchaseOrder::class)($s->staff, $s->order);
    }],
    'ship an approved order' => [function (OrderScenario $s) {
        $s->advanceTo(PoStatus::Approved);
        app(ShipPurchaseOrder::class)($s->staff, $s->order, 'TRK-1');
    }],
    'complete a packing order' => [function (OrderScenario $s) {
        $s->advanceTo(PoStatus::Packing)->complete();
    }],
    'submit twice' => [function (OrderScenario $s) {
        $s->submit();
        app(SubmitPurchaseOrder::class)($s->buyer, $s->order);
    }],
    'approve twice' => [function (OrderScenario $s) {
        $s->advanceTo(PoStatus::Approved);
        app(ApprovePurchaseOrder::class)($s->finance, $s->order);
    }],
]);

it('does not consume an invoice number when completing is not allowed', function () {
    $first = OrderScenario::draft(tag: 'A')->advanceTo(PoStatus::Completed);
    $notReady = OrderScenario::draft(tag: 'B')->advanceTo(PoStatus::Packing);

    expect(fn () => $notReady->complete())->toThrow(InvalidStatusTransitionException::class);

    $third = OrderScenario::draft(tag: 'C')->advanceTo(PoStatus::Completed);
    $numberOf = fn (string $invoice): int => (int) substr($invoice, -6);

    expect($numberOf($third->order->invoice_number))->toBe($numberOf($first->order->invoice_number) + 1);
});

it('protects a submitted order from the draft expiry job', function () {
    $s = OrderScenario::draft()->submit();
    DB::table('purchase_orders')->where('id', $s->order->id)->update(['reservation_expires_at' => now()->subDay()]);

    expect(app(ExpireStaleDraftOrders::class)())->toBe(0)
        ->and($s->order->refresh()->status)->toBe(PoStatus::PendingApproval)
        ->and($s->inventory->refresh()->qty_reserved)->toBe(50);
});
