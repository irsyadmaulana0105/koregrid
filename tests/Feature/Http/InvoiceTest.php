<?php

use App\Domain\Ordering\Enums\PoStatus;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\OrderScenario;

beforeEach(function () {
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);
});

it('shows the invoice of a completed order to the buyer and to finance', function () {
    $s = OrderScenario::draft(quantity: 50)->advanceTo(PoStatus::Completed);

    foreach ([$s->buyer, $s->finance] as $user) {
        $this->actingAs($user)->get("/orders/{$s->order->uuid}/invoice")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Orders/Invoice')
                ->where('invoice.invoice_number', $s->order->invoice_number)
                ->where('invoice.po_number', $s->order->po_number)
                ->where('invoice.subtotal', '4250000.00')
                ->where('invoice.tax_amount', '467500.00')
                ->where('invoice.total_amount', '4717500.00')
                ->where('invoice.tax_percent', '11')
                ->where('invoice.items.0.sku', 'F-SKU-1'));
    }
});

it('answers 404 while the order has no invoice yet', function () {
    $s = OrderScenario::draft()->advanceTo(PoStatus::Shipped);

    $this->actingAs($s->finance)->get("/orders/{$s->order->uuid}/invoice")->assertNotFound();
    $this->actingAs($s->buyer)->get("/orders/{$s->order->uuid}/invoice")->assertNotFound();
});

it('answers 404 for the invoice of another company', function () {
    $one = OrderScenario::draft(tag: '1')->advanceTo(PoStatus::Completed);
    $two = OrderScenario::draft(tag: '2');

    $this->actingAs($two->buyer)->get("/orders/{$one->order->uuid}/invoice")->assertNotFound();
});

it('forbids warehouse staff, who may view the order but not invoices', function () {
    $s = OrderScenario::draft()->advanceTo(PoStatus::Completed);

    $this->actingAs($s->staff)->get("/orders/{$s->order->uuid}")->assertOk();
    $this->actingAs($s->staff)->get("/orders/{$s->order->uuid}/invoice")->assertForbidden();
});

it('answers 404 for an unknown or malformed order id', function () {
    $s = OrderScenario::draft();

    $this->actingAs($s->finance)->get('/orders/'.Str::uuid().'/invoice')->assertNotFound();
    $this->actingAs($s->finance)->get('/orders/bukan-uuid/invoice')->assertNotFound();
});

it('sends guests to the login page', function () {
    $this->get('/orders/'.Str::uuid().'/invoice')->assertRedirect('/login');
});
