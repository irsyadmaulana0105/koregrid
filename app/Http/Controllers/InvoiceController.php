<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Models\User;
use App\Domain\Ordering\Models\PurchaseOrder;
use App\Http\Presenters\OrderPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Invoice siap cetak. Urutan pemeriksaan disengaja: PO yang tidak boleh dilihat dijawab 404 lebih dulu
 * (keberadaannya tidak bocor), baru hak invoice, baru status PO.
 */
final class InvoiceController
{
    public function __invoke(Request $request, PurchaseOrder $order): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        abort_unless($user->can('view', $order), 404);
        abort_unless($user->can('invoice.view'), 403);
        abort_if($order->invoice_number === null, 404);

        $order->loadMissing('company');

        $taxPercent = rtrim(rtrim(bcmul((string) config('koregrid.tax_rate'), '100', 2), '0'), '.');

        return Inertia::render('Orders/Invoice', [
            'invoice' => OrderPresenter::detail($order, []) + [
                'billing_address' => (string) $order->company->billing_address,
                'tax_percent' => $taxPercent,
            ],
        ]);
    }
}
