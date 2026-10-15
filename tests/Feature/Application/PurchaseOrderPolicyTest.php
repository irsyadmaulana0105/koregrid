<?php

use App\Domain\Identity\Enums\PricingTier;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\Support\Fixtures;
use Tests\Support\OrderScenario;

function canView(User $user, OrderScenario $scenario): bool
{
    return Gate::forUser($user)->allows('view', $scenario->order);
}

it('lets people see the orders they are meant to see', function (string $who, bool $expected) {
    $s = OrderScenario::draft();

    $user = match ($who) {
        'buyer of the same company' => $s->buyer,
        'buyer of another company' => Fixtures::user(RoleName::Buyer, Fixtures::company(PricingTier::Silver, 'F-CMP-OTHER')),
        'finance' => $s->finance,
        'staff of the order warehouse' => $s->staff,
        'staff of another warehouse' => Fixtures::user(RoleName::WarehouseStaff, null, Fixtures::warehouse('F-WH-OTHER')),
        'staff without a fixed warehouse' => Fixtures::user(RoleName::WarehouseStaff),
        'super admin' => Fixtures::user(RoleName::SuperAdmin),
        'user without a role' => User::factory()->create(['company_id' => $s->company->id]),
    };

    expect(canView($user, $s))->toBe($expected);
})->with([
    'buyer of the same company' => ['buyer of the same company', true],
    'buyer of another company' => ['buyer of another company', false],
    'finance' => ['finance', true],
    'staff of the order warehouse' => ['staff of the order warehouse', true],
    'staff of another warehouse' => ['staff of another warehouse', false],
    'staff without a fixed warehouse' => ['staff without a fixed warehouse', true],
    'super admin' => ['super admin', true],
    'user without a role' => ['user without a role', false],
]);
