<?php

use App\Domain\Ordering\Enums\PoStatus;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\OrderScenario;

beforeEach(function () {
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);
});

it('shows the order, its items and the allowed actions to the buyer', function () {
    $s = OrderScenario::draft();

    $this->actingAs($s->buyer)->get("/orders/{$s->order->uuid}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Orders/Show')
            ->where('order.po_number', $s->order->po_number)
            ->where('order.status', 'draft')
            ->where('order.items.0.sku', 'F-SKU-1')
            ->where('order.items.0.quantity', 50)
            ->where('order.items.0.line_total', '4250000.00')
            ->where('order.actions.submit', true)
            ->where('order.actions.cancel', true)
            ->where('order.actions.approve', false)
            ->where('order.actions.complete', false));
});

it('offers approve and reject, but not cancel, to finance on a pending order', function () {
    $s = OrderScenario::draft()->submit();

    $this->actingAs($s->finance)->get("/orders/{$s->order->uuid}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('order.actions.approve', true)
            ->where('order.actions.reject', true)
            ->where('order.actions.cancel', false)
            ->where('order.actions.submit', false));
});

it('offers cancel but not approve to the buyer on a pending order', function () {
    $s = OrderScenario::draft()->submit();

    $this->actingAs($s->buyer)->get("/orders/{$s->order->uuid}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('order.actions.cancel', true)
            ->where('order.actions.approve', false)
            ->where('order.actions.reject', false));
});

it('offers packing to the warehouse staff on an approved order', function () {
    $s = OrderScenario::draft()->advanceTo(PoStatus::Approved);

    $this->actingAs($s->staff)->get("/orders/{$s->order->uuid}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('order.actions.pack', true)
            ->where('order.actions.ship', false));
});

it('answers 404 for an order of another company', function () {
    $one = OrderScenario::draft(tag: '1');
    $two = OrderScenario::draft(tag: '2');

    $this->actingAs($two->buyer)->get("/orders/{$one->order->uuid}")->assertNotFound();
});

it('answers 404 for warehouse staff of another warehouse', function () {
    $one = OrderScenario::draft(tag: '1');
    $two = OrderScenario::draft(tag: '2');

    $this->actingAs($two->staff)->get("/orders/{$one->order->uuid}")->assertNotFound();
});

it('answers 404 for an unknown or malformed order id', function () {
    $s = OrderScenario::draft();

    $this->actingAs($s->finance)->get('/orders/'.Str::uuid())->assertNotFound();
    $this->actingAs($s->finance)->get('/orders/bukan-uuid')->assertNotFound();
});

it('sends guests to the login page', function () {
    $this->get('/orders/'.Str::uuid())->assertRedirect('/login');
});
