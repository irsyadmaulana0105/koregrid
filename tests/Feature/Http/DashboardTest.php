<?php

use App\Domain\Identity\Enums\PermissionName;
use App\Domain\Identity\Enums\PricingTier;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Ordering\Enums\PoStatus;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Fixtures;

beforeEach(function () {
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);
});

it('shares the role and a full permission map for a super admin', function () {
    $admin = Fixtures::user(RoleName::SuperAdmin);

    $this->actingAs($admin)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('auth.user.roles', [RoleName::SuperAdmin->value])
            ->where('auth.user.can', fn ($can) => $can->count() === count(PermissionName::cases())
                && $can->every(fn ($allowed) => $allowed === true))
            ->where('stats', fn ($stats) => $stats->count() === count(PoStatus::cases())
                && $stats->every(fn ($total) => $total === 0)));
});

it('shows the company tier and no permissions for a user without a role', function () {
    $buyer = Fixtures::buyer(Fixtures::company(PricingTier::Gold));

    $this->actingAs($buyer)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('auth.user.company.pricing_tier', PricingTier::Gold->value)
            ->where('auth.user.warehouse', null)
            ->where('auth.user.can', fn ($can) => $can->every(fn ($allowed) => $allowed === false))
            ->where('stats', fn ($stats) => $stats->every(fn ($total) => $total === 0)));
});
