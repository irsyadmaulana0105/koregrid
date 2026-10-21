<?php

use App\Domain\Ordering\Enums\PoStatus;
use Illuminate\Support\Str;
use Tests\Support\OrderScenario;

beforeEach(function () {
    $this->withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);
});

function orderPage(OrderScenario $s): string
{
    return "/orders/{$s->order->uuid}";
}

function orderActionUrl(OrderScenario $s, string $action): string
{
    return orderPage($s)."/{$action}";
}

it('carries an order through the whole workflow over HTTP', function () {
    $s = OrderScenario::draft(quantity: 50, onHand: 200);
    $page = orderPage($s);

    $this->actingAs($s->buyer)->from($page)->post(orderActionUrl($s, 'submit'))
        ->assertRedirect($page)->assertSessionHas('success');
    expect($s->order->refresh()->status->value)->toBe('pending_approval');

    $this->actingAs($s->finance)->from($page)->post(orderActionUrl($s, 'approve'))->assertRedirect($page);
    expect($s->order->refresh()->status->value)->toBe('approved');

    $this->actingAs($s->staff)->from($page)->post(orderActionUrl($s, 'pack'))->assertRedirect($page);
    expect($s->order->refresh()->status->value)->toBe('packing');

    $this->actingAs($s->staff)->from($page)->post(orderActionUrl($s, 'ship'), ['tracking_number' => 'TRK-777'])
        ->assertRedirect($page);
    expect($s->order->refresh()->status->value)->toBe('shipped')
        ->and($s->order->tracking_number)->toBe('TRK-777');

    $this->actingAs($s->buyer)->from($page)->post(orderActionUrl($s, 'complete'))->assertRedirect($page);
    $order = $s->order->refresh();
    $inventory = $s->inventory->refresh();

    expect($order->status->value)->toBe('completed')
        ->and($order->invoice_number)->not->toBeNull()
        ->and($inventory->qty_on_hand)->toBe(150)
        ->and($inventory->qty_reserved)->toBe(0);
});

it('requires a reason to reject, strips markup and releases the stock', function () {
    $s = OrderScenario::draft(quantity: 50, onHand: 200)->submit();
    $page = orderPage($s);

    $this->actingAs($s->finance)->from($page)->post(orderActionUrl($s, 'reject'), [])
        ->assertSessionHasErrors('reason');
    expect($s->order->refresh()->status->value)->toBe('pending_approval')
        ->and($s->inventory->refresh()->qty_reserved)->toBe(50);

    $this->actingAs($s->finance)->from($page)
        ->post(orderActionUrl($s, 'reject'), ['reason' => '<b>Harga</b> tidak sesuai'])
        ->assertRedirect($page);

    expect($s->order->refresh()->status->value)->toBe('rejected')
        ->and($s->order->rejection_reason)->toBe('Harga tidak sesuai')
        ->and($s->inventory->refresh()->qty_reserved)->toBe(0);
});

it('requires a tracking number of at most 100 characters to ship', function () {
    $s = OrderScenario::draft()->advanceTo(PoStatus::Packing);
    $page = orderPage($s);

    $this->actingAs($s->staff)->from($page)->post(orderActionUrl($s, 'ship'), [])
        ->assertSessionHasErrors('tracking_number');

    $this->actingAs($s->staff)->from($page)
        ->post(orderActionUrl($s, 'ship'), ['tracking_number' => str_repeat('A', 101)])
        ->assertSessionHasErrors('tracking_number');

    expect($s->order->refresh()->status->value)->toBe('packing');
});

it('lets the buyer cancel a draft and releases the stock', function () {
    $s = OrderScenario::draft(quantity: 50, onHand: 200);
    $page = orderPage($s);

    $this->actingAs($s->buyer)->from($page)->post(orderActionUrl($s, 'cancel'))
        ->assertRedirect($page)->assertSessionHas('success');

    expect($s->order->refresh()->status->value)->toBe('cancelled')
        ->and($s->inventory->refresh()->qty_reserved)->toBe(0);
});

it('forbids actors who lack the permission for the action', function () {
    $s = OrderScenario::draft();

    $this->actingAs($s->finance)->post(orderActionUrl($s, 'submit'))->assertForbidden();
    $this->actingAs($s->finance)->post(orderActionUrl($s, 'cancel'))->assertForbidden();
    $this->actingAs($s->staff)->post(orderActionUrl($s, 'approve'))->assertForbidden();

    $s->submit();

    $this->actingAs($s->buyer)->post(orderActionUrl($s, 'approve'))->assertForbidden();
    $this->actingAs($s->buyer)->post(orderActionUrl($s, 'pack'))->assertForbidden();

    expect($s->order->refresh()->status->value)->toBe('pending_approval');
});

it('hides orders of other tenants behind 404 for every action', function () {
    $one = OrderScenario::draft(tag: '1');
    $two = OrderScenario::draft(tag: '2');

    $this->actingAs($two->buyer)->post(orderActionUrl($one, 'cancel'))->assertNotFound();
    expect($one->order->refresh()->status->value)->toBe('draft');

    $one->advanceTo(PoStatus::Packing);

    $this->actingAs($two->staff)->post(orderActionUrl($one, 'ship'), ['tracking_number' => 'X'])->assertNotFound();
    expect($one->order->refresh()->status->value)->toBe('packing');
});

it('reports an invalid status transition as a message', function () {
    $s = OrderScenario::draft();
    $page = orderPage($s);

    $this->actingAs($s->finance)->from($page)->post(orderActionUrl($s, 'approve'))
        ->assertRedirect($page)
        ->assertSessionHasErrors('action');

    expect($s->order->refresh()->status->value)->toBe('draft');
});

it('sends guests to the login page', function () {
    $this->post('/orders/'.Str::uuid().'/approve')->assertRedirect('/login');
});

it('rate limits order actions per user', function () {
    $s = OrderScenario::draft();

    foreach (range(1, 30) as $ignored) {
        $this->actingAs($s->finance)->post(orderActionUrl($s, 'approve'))->assertStatus(302);
    }

    $this->actingAs($s->finance)->post(orderActionUrl($s, 'approve'))->assertStatus(429);
});
