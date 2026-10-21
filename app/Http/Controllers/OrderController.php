<?php

namespace App\Http\Controllers;

use App\Application\Ordering\Actions\CreateDraftPurchaseOrder;
use App\Application\Ordering\Actions\ResolveOrderLines;
use App\Domain\Identity\Models\User;
use App\Http\Requests\StoreOrderRequest;
use DomainException;
use Illuminate\Http\RedirectResponse;

final class OrderController
{
    public function store(
        StoreOrderRequest $request,
        ResolveOrderLines $resolveLines,
        CreateDraftPurchaseOrder $createDraft,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        try {
            $order = $createDraft($user, $resolveLines($request->lines()));
        } catch (DomainException $e) {
            // Pelanggaran aturan bisnis (MOQ, stok, dll.) kembali ke halaman asal sebagai pesan.
            return back()->withErrors(['cart' => $e->getMessage()]);
        }

        return redirect('/dashboard')->with('success', "Draft {$order->po_number} berhasil dibuat dan stok sudah dikunci.");
    }
}
