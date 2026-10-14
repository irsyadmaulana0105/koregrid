<?php

use App\Application\Ordering\Actions\TransitionPurchaseOrder;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Identity\Enums\PricingTier;
use App\Domain\Ordering\Enums\PoStatus;
use App\Domain\Ordering\Exceptions\InvalidStatusTransitionException;
use App\Domain\Ordering\Models\PurchaseOrder;
use Tests\Support\Fixtures;

function draftOrderForTransition(): PurchaseOrder
{
    $company = Fixtures::company(PricingTier::Gold);

    return PurchaseOrder::create([
        'po_number' => 'T-PO-1',
        'company_id' => $company->id,
        'buyer_id' => Fixtures::buyer($company)->id,
        'pricing_tier' => PricingTier::Gold,
    ]);
}

it('moves the order along a valid transition and records who, from what, to what', function () {
    $order = draftOrderForTransition();

    app(TransitionPurchaseOrder::class)($order, PoStatus::PendingApproval, ['note' => 'siap ditinjau']);

    $audit = AuditLog::where('event', 'status_changed')->sole();
    expect($order->refresh()->status)->toBe(PoStatus::PendingApproval)
        ->and($audit->old_values)->toBe(['status' => 'draft'])
        ->and($audit->new_values)->toEqual(['status' => 'pending_approval', 'note' => 'siap ditinjau'])
        ->and($audit->auditable_id)->toBe($order->id);
});

it('refuses an invalid transition and changes nothing', function () {
    $order = draftOrderForTransition();

    expect(fn () => app(TransitionPurchaseOrder::class)($order, PoStatus::Shipped))
        ->toThrow(InvalidStatusTransitionException::class);

    expect($order->refresh()->status)->toBe(PoStatus::Draft)->and(AuditLog::count())->toBe(0);
});
