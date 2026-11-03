<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Models\User;
use App\Domain\Ordering\Models\PurchaseOrder;
use App\Http\Presenters\OrderPresenter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unduh invoice sebagai PDF. Urutan pemeriksaan sama dengan halaman invoice:
 * PO yang tidak boleh dilihat dijawab 404 lebih dulu, baru hak download, baru status PO.
 */
final class InvoiceDownloadController
{
    public function __invoke(Request $request, PurchaseOrder $order): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        abort_unless($user->can('view', $order), 404);
        abort_unless($user->can('invoice.download'), 403);
        abort_if($order->invoice_number === null, 404);

        $order->loadMissing('company');

        $invoice = OrderPresenter::detail($order, []) + [
            'billing_address' => (string) $order->company->billing_address,
            'tax_percent' => OrderPresenter::taxPercent(),
        ];

        return Pdf::loadView('invoice-pdf', ['invoice' => $invoice])
            ->setPaper('a4')
            ->download($order->invoice_number.'.pdf');
    }
}
