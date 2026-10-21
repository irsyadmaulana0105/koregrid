<?php

use App\Application\Ordering\Queries\VisibleOrdersQuery;
use App\Domain\Identity\Enums\PricingTier;
use App\Domain\Identity\Enums\RoleName;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Fixtures;
use Tests\Support\OrderScenario;

beforeEach(function () {
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);
});

it('lists exactly the orders the policy lets each actor view', function () {
    $one = OrderScenario::draft(tag: '1');
    $two = OrderScenario::draft(tag: '2');

    $actors = [
        'super admin' => Fixtures::user(RoleName::SuperAdmin),
        'finance' => $one->finance,
        'staf gudang 1' => $one->staff,
        'staf gudang 2' => $two->staff,
        'staf tanpa gudang' => Fixtures::user(RoleName::WarehouseStaff),
        'buyer 1' => $one->buyer,
        'buyer 2' => $two->buyer,
        'user tanpa role' => Fixtures::buyer(Fixtures::company(PricingTier::Bronze, 'F-CMP-X')),
    ];

    $expected = [];
    $actual = [];
    foreach ($actors as $label => $actor) {
        $expected[$label] = collect([$one->order, $two->order])
            ->filter(fn ($order) => Gate::forUser($actor)->allows('view', $order))
            ->pluck('id')->sort()->values()->all();

        $actual[$label] = app(VisibleOrdersQuery::class)($actor)
            ->pluck('id')->sort()->values()->all();
    }

    expect($actual)->toEqual($expected);

    // Pengaman agar perbandingan di atas tidak kosong makna.
    expect($actual['finance'])->toHaveCount(2)
        ->and($actual['buyer 1'])->toHaveCount(1)
        ->and($actual['staf gudang 1'])->toHaveCount(1)
        ->and($actual['user tanpa role'])->toBeEmpty();
});

it('counts only the orders the user may see on the dashboard', function () {
    $one = OrderScenario::draft(tag: '1');
    OrderScenario::draft(tag: '2')->submit();

    $this->actingAs($one->buyer)->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.draft', 1)
            ->where('stats.pending_approval', 0));

    $this->actingAs($one->finance)->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.draft', 1)
            ->where('stats.pending_approval', 1));
});
