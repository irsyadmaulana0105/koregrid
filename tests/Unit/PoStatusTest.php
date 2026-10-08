<?php

use App\Domain\Ordering\Enums\PoStatus;

it('allows only the documented transitions', function (PoStatus $from, array $allowed) {
    expect($from->allowedTransitions())->toBe($allowed);
})->with([
    'draft' => [PoStatus::Draft, [PoStatus::PendingApproval, PoStatus::Cancelled]],
    'pending approval' => [PoStatus::PendingApproval, [PoStatus::Approved, PoStatus::Rejected, PoStatus::Cancelled]],
    'approved' => [PoStatus::Approved, [PoStatus::Packing]],
    'packing' => [PoStatus::Packing, [PoStatus::Shipped]],
    'shipped' => [PoStatus::Shipped, [PoStatus::Completed]],
    'completed' => [PoStatus::Completed, []],
    'rejected' => [PoStatus::Rejected, []],
    'cancelled' => [PoStatus::Cancelled, []],
]);

it('never transitions to itself', function (PoStatus $status) {
    expect($status->canTransitionTo($status))->toBeFalse();
})->with(PoStatus::cases());

it('does not allow skipping or going backwards', function (PoStatus $from, PoStatus $to) {
    expect($from->canTransitionTo($to))->toBeFalse();
})->with([
    'draft to approved' => [PoStatus::Draft, PoStatus::Approved],
    'approved to shipped' => [PoStatus::Approved, PoStatus::Shipped],
    'shipped to packing' => [PoStatus::Shipped, PoStatus::Packing],
    'approved to draft' => [PoStatus::Approved, PoStatus::Draft],
    'draft to rejected' => [PoStatus::Draft, PoStatus::Rejected],
    'approved to cancelled' => [PoStatus::Approved, PoStatus::Cancelled],
    'packing to cancelled' => [PoStatus::Packing, PoStatus::Cancelled],
    'completed to anything' => [PoStatus::Completed, PoStatus::Draft],
]);

it('treats completed, rejected and cancelled as terminal', function () {
    $terminal = array_values(array_filter(PoStatus::cases(), fn (PoStatus $s): bool => $s->isTerminal()));

    expect($terminal)->toBe([PoStatus::Completed, PoStatus::Rejected, PoStatus::Cancelled]);
});

it('holds the stock reservation until the order ships', function () {
    $holding = array_values(array_filter(PoStatus::cases(), fn (PoStatus $s): bool => $s->holdsReservation()));

    expect($holding)->toBe([PoStatus::Draft, PoStatus::PendingApproval, PoStatus::Approved, PoStatus::Packing]);
});

it('uses the same values as the CHECK constraint in the migration', function () {
    $migration = (string) file_get_contents(
        dirname(__DIR__, 2).'/database/migrations/2026_10_08_000007_create_purchase_orders_table.php',
    );
    preg_match("/status IN \(([^)]*)\)/", $migration, $matches);
    $inMigration = array_map(fn (string $v): string => trim($v, " '"), explode(',', $matches[1]));

    expect($inMigration)->toBe(array_map(fn (PoStatus $s): string => $s->value, PoStatus::cases()));
});
