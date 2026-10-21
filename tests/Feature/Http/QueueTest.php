<?php

use App\Domain\Identity\Enums\RoleName;
use App\Domain\Ordering\Enums\PoStatus;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Fixtures;
use Tests\Support\OrderScenario;

beforeEach(function () {
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);
});

it('shows finance only the orders waiting for approval', function () {
    $one = OrderScenario::draft(tag: '1')->submit();
    OrderScenario::draft(tag: '2'); // masih draft: tidak masuk antrean

    $this->actingAs($one->finance)->get('/queue')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Queue')
            ->has('approval', 1)
            ->where('approval.0.po_number', $one->order->po_number)
            ->where('fulfillment', null));
});

it('shows warehouse staff approved and packing orders of their own warehouse only', function () {
    $one = OrderScenario::draft(tag: '1')->advanceTo(PoStatus::Approved);
    OrderScenario::draft(tag: '2')->advanceTo(PoStatus::Approved);

    $this->actingAs($one->staff)->get('/queue')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('fulfillment', 1)
            ->where('fulfillment.0.po_number', $one->order->po_number)
            ->where('approval', null));

    $one->startPacking();

    $this->actingAs($one->staff)->get('/queue')
        ->assertInertia(fn (Assert $page) => $page
            ->has('fulfillment', 1)
            ->where('fulfillment.0.status', 'packing'));
});

it('drops an order from the warehouse queue once it is shipped', function () {
    $s = OrderScenario::draft()->advanceTo(PoStatus::Shipped);

    $this->actingAs($s->staff)->get('/queue')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('fulfillment', 0));
});

it('shows a super admin both queues', function () {
    OrderScenario::draft(tag: '1')->submit();

    $this->actingAs(Fixtures::user(RoleName::SuperAdmin))->get('/queue')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('approval', 1)->has('fulfillment', 0));
});

it('forbids a buyer', function () {
    $s = OrderScenario::draft();

    $this->actingAs($s->buyer)->get('/queue')->assertForbidden();
});

it('sends guests to the login page', function () {
    $this->get('/queue')->assertRedirect('/login');
});
