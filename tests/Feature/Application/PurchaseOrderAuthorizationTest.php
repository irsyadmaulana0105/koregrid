<?php

use App\Application\Ordering\Actions\ApprovePurchaseOrder;
use App\Application\Ordering\Actions\CancelPurchaseOrder;
use App\Application\Ordering\Actions\CompletePurchaseOrder;
use App\Application\Ordering\Actions\CreateDraftPurchaseOrder;
use App\Application\Ordering\Actions\RejectPurchaseOrder;
use App\Application\Ordering\Actions\ShipPurchaseOrder;
use App\Application\Ordering\Actions\StartPackingPurchaseOrder;
use App\Application\Ordering\Actions\SubmitPurchaseOrder;
use App\Application\Ordering\DTOs\OrderLineData;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Enums\PricingTier;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Models\User;
use App\Domain\Ordering\Enums\PoStatus;
use App\Domain\Ordering\Exceptions\InvalidPurchaseOrderException;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\Fixtures;
use Tests\Support\OrderScenario;

function buyerOfAnotherCompany(): User
{
    return Fixtures::user(RoleName::Buyer, Fixtures::company(PricingTier::Silver, 'F-CMP-OTHER'));
}

function staffOfAnotherWarehouse(): User
{
    return Fixtures::user(RoleName::WarehouseStaff, null, Fixtures::warehouse('F-WH-OTHER'));
}

it('refuses the wrong actor and changes nothing', function (Closure $attempt, PoStatus $state) {
    $s = OrderScenario::draft()->advanceTo($state);
    $auditEntries = AuditLog::count();

    expect(fn () => $attempt($s))->toThrow(AuthorizationException::class);

    expect($s->order->refresh()->status)->toBe($state)
        ->and(AuditLog::count())->toBe($auditEntries);
})->with([
    'buyer cannot approve' => [fn (OrderScenario $s) => app(ApprovePurchaseOrder::class)($s->buyer, $s->order), PoStatus::PendingApproval],
    'warehouse cannot approve' => [fn (OrderScenario $s) => app(ApprovePurchaseOrder::class)($s->staff, $s->order), PoStatus::PendingApproval],
    'buyer cannot reject' => [fn (OrderScenario $s) => app(RejectPurchaseOrder::class)($s->buyer, $s->order, 'x'), PoStatus::PendingApproval],
    'warehouse cannot reject' => [fn (OrderScenario $s) => app(RejectPurchaseOrder::class)($s->staff, $s->order, 'x'), PoStatus::PendingApproval],
    'finance cannot pack' => [fn (OrderScenario $s) => app(StartPackingPurchaseOrder::class)($s->finance, $s->order), PoStatus::Approved],
    'buyer cannot pack' => [fn (OrderScenario $s) => app(StartPackingPurchaseOrder::class)($s->buyer, $s->order), PoStatus::Approved],
    'finance cannot ship' => [fn (OrderScenario $s) => app(ShipPurchaseOrder::class)($s->finance, $s->order, 'TRK-1'), PoStatus::Packing],
    'buyer cannot ship' => [fn (OrderScenario $s) => app(ShipPurchaseOrder::class)($s->buyer, $s->order, 'TRK-1'), PoStatus::Packing],
    'finance cannot complete' => [fn (OrderScenario $s) => app(CompletePurchaseOrder::class)($s->finance, $s->order), PoStatus::Shipped],
    'warehouse cannot complete' => [fn (OrderScenario $s) => app(CompletePurchaseOrder::class)($s->staff, $s->order), PoStatus::Shipped],
    'finance cannot submit' => [fn (OrderScenario $s) => app(SubmitPurchaseOrder::class)($s->finance, $s->order), PoStatus::Draft],
    'warehouse cannot submit' => [fn (OrderScenario $s) => app(SubmitPurchaseOrder::class)($s->staff, $s->order), PoStatus::Draft],
    'finance cannot cancel' => [fn (OrderScenario $s) => app(CancelPurchaseOrder::class)($s->finance, $s->order), PoStatus::Draft],
    'user without a role cannot submit' => [fn (OrderScenario $s) => app(SubmitPurchaseOrder::class)(User::factory()->create(['company_id' => $s->company->id]), $s->order), PoStatus::Draft],
]);

it('keeps companies apart: a buyer cannot act on another company order', function (Closure $attempt, PoStatus $state) {
    $s = OrderScenario::draft()->advanceTo($state);

    expect(fn () => $attempt($s))->toThrow(AuthorizationException::class);

    expect($s->order->refresh()->status)->toBe($state)
        ->and($s->inventory->refresh()->qty_reserved)->toBe($state === PoStatus::Shipped ? 0 : 50);
})->with([
    'submit' => [fn (OrderScenario $s) => app(SubmitPurchaseOrder::class)(buyerOfAnotherCompany(), $s->order), PoStatus::Draft],
    'cancel a draft' => [fn (OrderScenario $s) => app(CancelPurchaseOrder::class)(buyerOfAnotherCompany(), $s->order), PoStatus::Draft],
    'cancel a pending order' => [fn (OrderScenario $s) => app(CancelPurchaseOrder::class)(buyerOfAnotherCompany(), $s->order), PoStatus::PendingApproval],
    'complete' => [fn (OrderScenario $s) => app(CompletePurchaseOrder::class)(buyerOfAnotherCompany(), $s->order), PoStatus::Shipped],
]);

it('limits warehouse staff to orders that contain stock from their warehouse', function (Closure $attempt, PoStatus $state) {
    $s = OrderScenario::draft()->advanceTo($state);

    expect(fn () => $attempt($s))->toThrow(AuthorizationException::class);

    expect($s->order->refresh()->status)->toBe($state);
})->with([
    'pack' => [fn (OrderScenario $s) => app(StartPackingPurchaseOrder::class)(staffOfAnotherWarehouse(), $s->order), PoStatus::Approved],
    'ship' => [fn (OrderScenario $s) => app(ShipPurchaseOrder::class)(staffOfAnotherWarehouse(), $s->order, 'TRK-1'), PoStatus::Packing],
]);

it('lets warehouse staff without a fixed warehouse act on any order', function () {
    $s = OrderScenario::draft()->advanceTo(PoStatus::Approved);
    $floatingStaff = Fixtures::user(RoleName::WarehouseStaff);

    app(StartPackingPurchaseOrder::class)($floatingStaff, $s->order);

    expect($s->order->refresh()->status)->toBe(PoStatus::Packing)
        ->and($s->order->packed_by)->toBe($floatingStaff->id);
});

it('lets Super Admin act on any order', function () {
    $s = OrderScenario::draft()->submit();
    $admin = Fixtures::user(RoleName::SuperAdmin);

    app(ApprovePurchaseOrder::class)($admin, $s->order);

    expect($s->order->refresh()->status)->toBe(PoStatus::Approved)->and($s->order->approved_by)->toBe($admin->id);
});

it('does not let the creator of an order approve it, not even Super Admin', function () {
    $company = Fixtures::company(PricingTier::Gold);
    $warehouse = Fixtures::warehouse();
    $product = Fixtures::product();
    Fixtures::stock($product, $warehouse, 100);
    $admin = Fixtures::user(RoleName::SuperAdmin, $company);

    $order = app(CreateDraftPurchaseOrder::class)($admin, [new OrderLineData($product->id, $warehouse->id, 50)]);
    app(SubmitPurchaseOrder::class)($admin, $order);

    expect(fn () => app(ApprovePurchaseOrder::class)($admin, $order))
        ->toThrow(InvalidPurchaseOrderException::class);

    expect($order->refresh()->status)->toBe(PoStatus::PendingApproval)->and($order->approved_by)->toBeNull();
});
