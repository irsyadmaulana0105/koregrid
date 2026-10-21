<?php

use App\Domain\Identity\Enums\PricingTier;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Fixtures;
use Tests\Support\OrderScenario;

beforeEach(function () {
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);
});

it('lists only the orders of the buyer company', function () {
    $one = OrderScenario::draft(tag: '1');
    OrderScenario::draft(tag: '2');

    $this->actingAs($one->buyer)->get('/orders')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Orders/Index')
            ->where('orders.total', 1)
            ->where('orders.data.0.po_number', $one->order->po_number)
            ->where('orders.data.0.status', 'draft'));
});

it('lists every order for finance', function () {
    $one = OrderScenario::draft(tag: '1');
    OrderScenario::draft(tag: '2');

    $this->actingAs($one->finance)->get('/orders')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('orders.total', 2));
});

it('filters by status and ignores an unknown filter', function () {
    $one = OrderScenario::draft(tag: '1');
    $two = OrderScenario::draft(tag: '2')->submit();

    $this->actingAs($one->finance)->get('/orders?status=pending_approval')
        ->assertInertia(fn (Assert $page) => $page
            ->where('orders.total', 1)
            ->where('orders.data.0.po_number', $two->order->po_number)
            ->where('filters.status', 'pending_approval'));

    $this->actingAs($one->finance)->get('/orders?status=bogus')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('orders.total', 2)->where('filters.status', null));

    $this->actingAs($one->finance)->get('/orders?status[]=draft')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('orders.total', 2));
});

it('shows an empty list to a user who may not view any order', function () {
    OrderScenario::draft();

    $this->actingAs(Fixtures::buyer(Fixtures::company(PricingTier::Bronze, 'F-CMP-X')))->get('/orders')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('orders.total', 0));
});

it('sends guests to the login page', function () {
    $this->get('/orders')->assertRedirect('/login');
});
