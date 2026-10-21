<?php

namespace App\Http\Controllers;

use App\Application\Ordering\Actions\ApprovePurchaseOrder;
use App\Application\Ordering\Actions\CancelPurchaseOrder;
use App\Application\Ordering\Actions\CompletePurchaseOrder;
use App\Application\Ordering\Actions\RejectPurchaseOrder;
use App\Application\Ordering\Actions\ShipPurchaseOrder;
use App\Application\Ordering\Actions\StartPackingPurchaseOrder;
use App\Application\Ordering\Actions\SubmitPurchaseOrder;
use App\Domain\Identity\Models\User;
use App\Domain\Ordering\Models\PurchaseOrder;
use App\Http\Requests\CancelOrderRequest;
use App\Http\Requests\OrderActionRequest;
use App\Http\Requests\RejectOrderRequest;
use App\Http\Requests\ShipOrderRequest;
use Closure;
use DomainException;
use Illuminate\Http\RedirectResponse;

/**
 * Setiap aksi hanya memanggil use case. Hak akses ditegakkan di use case (Policy);
 * AuthorizationException menjadi 403 oleh framework, pelanggaran aturan bisnis menjadi pesan di halaman.
 */
final class OrderActionController
{
    public function submit(OrderActionRequest $request, PurchaseOrder $order, SubmitPurchaseOrder $action): RedirectResponse
    {
        return $this->perform($request, 'Pesanan diajukan untuk persetujuan Finance.', fn (User $user) => $action($user, $order));
    }

    public function cancel(CancelOrderRequest $request, PurchaseOrder $order, CancelPurchaseOrder $action): RedirectResponse
    {
        return $this->perform($request, 'Pesanan dibatalkan dan stok dilepas.', fn (User $user) => $action($user, $order, $request->reason()));
    }

    public function approve(OrderActionRequest $request, PurchaseOrder $order, ApprovePurchaseOrder $action): RedirectResponse
    {
        return $this->perform($request, 'Pesanan disetujui.', fn (User $user) => $action($user, $order));
    }

    public function reject(RejectOrderRequest $request, PurchaseOrder $order, RejectPurchaseOrder $action): RedirectResponse
    {
        return $this->perform($request, 'Pesanan ditolak dan stok dilepas.', fn (User $user) => $action($user, $order, $request->reason()));
    }

    public function pack(OrderActionRequest $request, PurchaseOrder $order, StartPackingPurchaseOrder $action): RedirectResponse
    {
        return $this->perform($request, 'Pesanan mulai dikemas.', fn (User $user) => $action($user, $order));
    }

    public function ship(ShipOrderRequest $request, PurchaseOrder $order, ShipPurchaseOrder $action): RedirectResponse
    {
        return $this->perform($request, 'Pesanan dikirim dan stok fisik dikeluarkan.', fn (User $user) => $action($user, $order, $request->trackingNumber()));
    }

    public function complete(OrderActionRequest $request, PurchaseOrder $order, CompletePurchaseOrder $action): RedirectResponse
    {
        return $this->perform($request, 'Pesanan selesai dan invoice diterbitkan.', fn (User $user) => $action($user, $order));
    }

    private function perform(OrderActionRequest $request, string $message, Closure $action): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        try {
            $action($user);
        } catch (DomainException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        return back()->with('success', $message);
    }
}
