<?php

namespace App\Http\Controllers;

use App\Application\Ordering\Actions\CreateDraftPurchaseOrder;
use App\Application\Ordering\Actions\ResolveOrderLines;
use App\Application\Ordering\Queries\AvailableOrderActions;
use App\Application\Ordering\Queries\VisibleOrdersQuery;
use App\Domain\Identity\Models\User;
use App\Domain\Ordering\Enums\PoStatus;
use App\Domain\Ordering\Models\PurchaseOrder;
use App\Http\Presenters\OrderPresenter;
use App\Http\Requests\StoreOrderRequest;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class OrderController
{
    public function index(Request $request, VisibleOrdersQuery $visibleOrders): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        // Filter yang tidak dikenal (atau bukan teks) diabaikan, bukan menyebabkan error.
        $raw = $request->query('status');
        $status = is_string($raw) ? PoStatus::tryFrom($raw) : null;

        $query = $visibleOrders($user)->with('company');

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        $orders = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (PurchaseOrder $order): array => OrderPresenter::row($order));

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'filters' => ['status' => $status?->value],
        ]);
    }

    public function show(Request $request, PurchaseOrder $order, AvailableOrderActions $actions): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        // 404, bukan 403: keberadaan PO yang tidak boleh dilihat tidak boleh bocor.
        abort_unless($user->can('view', $order), 404);

        return Inertia::render('Orders/Show', [
            'order' => OrderPresenter::detail($order, $actions($user, $order)),
        ]);
    }

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

        return redirect()->route('orders.show', $order)
            ->with('success', "Draft {$order->po_number} berhasil dibuat dan stok sudah dikunci. Ajukan untuk persetujuan Finance.");
    }
}
