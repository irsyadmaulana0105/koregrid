<?php

use App\Application\Inventory\Actions\CommitStock;
use App\Application\Inventory\Actions\ReleaseStock;
use App\Application\Inventory\Actions\ReserveStock;
use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Domain\Inventory\Exceptions\StockInvariantViolationException;
use Tests\Support\Fixtures;

function stockScenario(int $onHand = 10): array
{
    $product = Fixtures::product();
    $warehouse = Fixtures::warehouse();

    return [$product, $warehouse, Fixtures::stock($product, $warehouse, $onHand)];
}

it('reserves stock until nothing is left, then refuses', function () {
    [$product, $warehouse, $inventory] = stockScenario(10);
    $reserve = app(ReserveStock::class);

    $reserve($product->id, $warehouse->id, 4);
    $reserve($product->id, $warehouse->id, 6);

    expect(fn () => $reserve($product->id, $warehouse->id, 1))->toThrow(InsufficientStockException::class);

    $inventory->refresh();
    expect($inventory->qty_reserved)->toBe(10)->and($inventory->available)->toBe(0);
});

it('refuses to reserve more than what is available in one go', function () {
    [$product, $warehouse, $inventory] = stockScenario(10);

    expect(fn () => app(ReserveStock::class)($product->id, $warehouse->id, 11))
        ->toThrow(InsufficientStockException::class);

    expect($inventory->refresh()->qty_reserved)->toBe(0);
});

it('treats a missing inventory row as no stock', function () {
    $product = Fixtures::product();
    $warehouse = Fixtures::warehouse();

    expect(fn () => app(ReserveStock::class)($product->id, $warehouse->id, 1))
        ->toThrow(InsufficientStockException::class);
});

it('rejects non-positive quantities', function (string $action) {
    [$product, $warehouse] = stockScenario();

    expect(fn () => app($action)($product->id, $warehouse->id, 0))->toThrow(InvalidArgumentException::class);
})->with([ReserveStock::class, ReleaseStock::class, CommitStock::class]);

it('releases a reservation and never more than what was reserved', function () {
    [$product, $warehouse, $inventory] = stockScenario(10);
    app(ReserveStock::class)($product->id, $warehouse->id, 5);

    app(ReleaseStock::class)($product->id, $warehouse->id, 3);
    expect($inventory->refresh()->qty_reserved)->toBe(2);

    expect(fn () => app(ReleaseStock::class)($product->id, $warehouse->id, 3))
        ->toThrow(StockInvariantViolationException::class);
});

it('commits reserved stock by reducing on-hand and reserved together', function () {
    [$product, $warehouse, $inventory] = stockScenario(10);
    app(ReserveStock::class)($product->id, $warehouse->id, 5);

    app(CommitStock::class)($product->id, $warehouse->id, 5);

    $inventory->refresh();
    expect($inventory->qty_on_hand)->toBe(5)
        ->and($inventory->qty_reserved)->toBe(0)
        ->and($inventory->available)->toBe(5);
});

it('refuses to commit stock that was never reserved', function () {
    [$product, $warehouse, $inventory] = stockScenario(10);

    expect(fn () => app(CommitStock::class)($product->id, $warehouse->id, 1))
        ->toThrow(StockInvariantViolationException::class);

    expect($inventory->refresh()->qty_on_hand)->toBe(10);
});
