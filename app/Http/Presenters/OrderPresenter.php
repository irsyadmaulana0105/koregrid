<?php

namespace App\Http\Presenters;

use App\Domain\Catalog\Models\Product;
use App\Domain\Identity\Enums\PricingTier;
use App\Domain\Ordering\Enums\PoStatus;
use App\Domain\Ordering\Models\PurchaseOrder;
use App\Domain\Ordering\Models\PurchaseOrderItem;
use Carbon\CarbonInterface;

/**
 * Bentuk data PO untuk halaman: hanya field yang perlu ditampilkan, tanpa id berurutan.
 */
final class OrderPresenter
{
    /** Persen PPN (mis. `11`), dipakai halaman invoice dan PDF. */
    public static function taxPercent(): string
    {
        return rtrim(rtrim(bcmul((string) config('koregrid.tax_rate'), '100', 2), '0'), '.');
    }

    /** @return array<string, string|null> */
    public static function row(PurchaseOrder $order): array
    {
        /** @var PoStatus $status */
        $status = $order->status;

        return [
            'uuid' => (string) $order->uuid,
            'po_number' => (string) $order->po_number,
            'status' => $status->value,
            'company' => (string) $order->company->name,
            'total_amount' => (string) $order->total_amount,
            'created_at' => self::iso($order->created_at),
        ];
    }

    /**
     * @param  array<string, bool>  $actions
     * @return array<string, mixed>
     */
    public static function detail(PurchaseOrder $order, array $actions): array
    {
        $order->loadMissing(['company', 'buyer', 'items.warehouse']);

        // Produk yang sudah dihapus lunak tetap harus tampil pada PO lama.
        $products = Product::query()
            ->withTrashed()
            ->whereIn('id', $order->items->pluck('product_id')->all())
            ->get()
            ->keyBy('id');

        $items = $order->items
            ->sortBy('id')
            ->values()
            ->map(function (PurchaseOrderItem $item) use ($products): array {
                $product = $products->get($item->product_id);

                return [
                    'sku' => $product === null ? '-' : (string) $product->sku,
                    'name' => $product === null ? '-' : (string) $product->name,
                    'unit' => $product === null ? '' : (string) $product->unit,
                    'warehouse_code' => (string) $item->warehouse->code,
                    'warehouse_name' => (string) $item->warehouse->name,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (string) $item->unit_price,
                    'moq_applied' => (int) $item->moq_applied,
                    'line_total' => (string) $item->line_total,
                ];
            })
            ->all();

        /** @var PoStatus $status */
        $status = $order->status;
        /** @var PricingTier $tier */
        $tier = $order->pricing_tier;

        return [
            'uuid' => (string) $order->uuid,
            'po_number' => (string) $order->po_number,
            'status' => $status->value,
            'pricing_tier' => $tier->value,
            'company' => (string) $order->company->name,
            'buyer' => (string) $order->buyer->name,
            'subtotal' => (string) $order->subtotal,
            'tax_amount' => (string) $order->tax_amount,
            'total_amount' => (string) $order->total_amount,
            'reservation_expires_at' => self::iso($order->reservation_expires_at),
            'created_at' => self::iso($order->created_at),
            'submitted_at' => self::iso($order->submitted_at),
            'approved_at' => self::iso($order->approved_at),
            'rejected_at' => self::iso($order->rejected_at),
            'rejection_reason' => self::text($order->rejection_reason),
            'shipped_at' => self::iso($order->shipped_at),
            'tracking_number' => self::text($order->tracking_number),
            'completed_at' => self::iso($order->completed_at),
            'invoice_number' => self::text($order->invoice_number),
            'invoice_issued_at' => self::iso($order->invoice_issued_at),
            'items' => $items,
            'actions' => $actions,
        ];
    }

    private static function iso(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toIso8601String() : null;
    }

    private static function text(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
