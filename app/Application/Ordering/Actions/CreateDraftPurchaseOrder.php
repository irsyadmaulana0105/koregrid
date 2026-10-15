<?php

namespace App\Application\Ordering\Actions;

use App\Application\Catalog\Actions\ResolveTierPrice;
use App\Application\Inventory\Actions\ReserveStock;
use App\Application\Ordering\DTOs\OrderLineData;
use App\Domain\Audit\Contracts\AuditRecorder;
use App\Domain\Catalog\Exceptions\ProductNotAvailableException;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductTier;
use App\Domain\Identity\Enums\PricingTier;
use App\Domain\Identity\Models\Company;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Ordering\Exceptions\InvalidPurchaseOrderException;
use App\Domain\Ordering\Exceptions\MoqNotMetException;
use App\Domain\Ordering\Models\PurchaseOrder;
use App\Domain\Shared\Money;
use Illuminate\Support\Facades\DB;

/**
 * Membuat draft PO: harga mengikuti tier perusahaan pembeli, MOQ diperiksa,
 * stok dikunci per item, dan semuanya dalam satu transaksi (gagal di mana pun = tidak ada yang tersimpan).
 */
final class CreateDraftPurchaseOrder
{
    public function __construct(
        private readonly ResolveTierPrice $resolveTierPrice,
        private readonly ReserveStock $reserveStock,
        private readonly AuditRecorder $audit,
    ) {}

    /**
     * @param  list<OrderLineData>  $lines
     */
    public function __invoke(User $buyer, array $lines): PurchaseOrder
    {
        $company = $this->companyOf($buyer);
        $lines = $this->normalize($lines);

        return DB::transaction(function () use ($buyer, $company, $lines): PurchaseOrder {
            $products = $this->loadProducts($lines);
            $this->assertWarehousesAvailable($lines);

            /** @var PricingTier $tier */
            $tier = $company->pricing_tier;

            $prices = $this->resolvePrices($lines, $products, $tier);

            // Baris sudah terurut (produk, gudang): urutan penguncian yang konsisten mencegah deadlock antar PO paralel.
            foreach ($lines as $line) {
                ($this->reserveStock)($line->productId, $line->warehouseId, $line->quantity);
            }

            [$items, $subtotal] = $this->buildItems($lines, $prices);
            $tax = Money::percentage($subtotal, (string) config('koregrid.tax_rate'));

            $order = new PurchaseOrder([
                'po_number' => $this->nextPoNumber(),
                'company_id' => $company->id,
                'buyer_id' => $buyer->id,
                'pricing_tier' => $tier,
                'reservation_expires_at' => now()->addHours((int) config('koregrid.draft_reservation_ttl_hours')),
            ]);
            $order->forceFill([
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'total_amount' => Money::add($subtotal, $tax),
            ])->save();

            $order->items()->createMany($items);

            $this->audit->record('created', $order, null, [
                'po_number' => $order->po_number,
                'status' => 'draft',
                'pricing_tier' => $tier->value,
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'items' => $items,
            ], $buyer->id);

            return $order->load('items');
        });
    }

    private function companyOf(User $buyer): Company
    {
        $company = $buyer->company;

        if (! $buyer->is_active || $company === null || ! $company->is_active) {
            throw InvalidPurchaseOrderException::buyerCannotOrder();
        }

        return $company;
    }

    /**
     * Menggabungkan baris kembar (produk + gudang sama) dan mengurutkannya.
     *
     * @param  list<OrderLineData>  $lines
     * @return list<OrderLineData>
     */
    private function normalize(array $lines): array
    {
        $merged = [];

        foreach ($lines as $line) {
            if ($line->quantity < 1) {
                throw InvalidPurchaseOrderException::invalidQuantity();
            }

            $key = $line->productId.':'.$line->warehouseId;
            $merged[$key] = isset($merged[$key])
                ? new OrderLineData($line->productId, $line->warehouseId, $merged[$key]->quantity + $line->quantity)
                : $line;
        }

        if ($merged === []) {
            throw InvalidPurchaseOrderException::empty();
        }

        $sorted = array_values($merged);
        usort($sorted, fn (OrderLineData $a, OrderLineData $b): int => [$a->productId, $a->warehouseId] <=> [$b->productId, $b->warehouseId]);

        return $sorted;
    }

    /**
     * @param  list<OrderLineData>  $lines
     * @return array<int, Product>
     */
    private function loadProducts(array $lines): array
    {
        $ids = array_values(array_unique(array_map(fn (OrderLineData $l): int => $l->productId, $lines)));
        $found = Product::query()->with('tiers')->whereIn('id', $ids)->get()->keyBy('id');

        $products = [];
        foreach ($ids as $id) {
            $product = $found->get($id);
            if ($product === null) {
                throw ProductNotAvailableException::missing($id);
            }
            $products[$id] = $product;
        }

        return $products;
    }

    /** @param  list<OrderLineData>  $lines */
    private function assertWarehousesAvailable(array $lines): void
    {
        $ids = array_values(array_unique(array_map(fn (OrderLineData $l): int => $l->warehouseId, $lines)));
        $active = array_map('intval', Warehouse::query()->whereIn('id', $ids)->where('is_active', true)->pluck('id')->all());

        foreach ($ids as $id) {
            if (! in_array($id, $active, true)) {
                throw InvalidPurchaseOrderException::warehouseUnavailable($id);
            }
        }
    }

    /**
     * Harga tier per produk, dengan MOQ diperiksa terhadap jumlah total produk itu di semua gudang.
     *
     * @param  list<OrderLineData>  $lines
     * @param  array<int, Product>  $products
     * @return array<int, ProductTier>
     */
    private function resolvePrices(array $lines, array $products, PricingTier $tier): array
    {
        $quantities = [];
        foreach ($lines as $line) {
            $quantities[$line->productId] = ($quantities[$line->productId] ?? 0) + $line->quantity;
        }

        $prices = [];
        foreach ($quantities as $productId => $quantity) {
            $price = ($this->resolveTierPrice)($products[$productId], $tier);

            if ($quantity < $price->moq) {
                throw MoqNotMetException::for($products[$productId], $price->moq, $quantity);
            }

            $prices[$productId] = $price;
        }

        return $prices;
    }

    /**
     * Item PO membawa snapshot harga dan MOQ, sehingga perubahan harga kemudian tidak memengaruhi PO ini.
     *
     * @param  list<OrderLineData>  $lines
     * @param  array<int, ProductTier>  $prices
     * @return array{0: list<array<string, int|string>>, 1: string}
     */
    private function buildItems(array $lines, array $prices): array
    {
        $items = [];
        $subtotal = '0.00';

        foreach ($lines as $line) {
            $price = $prices[$line->productId];
            $unitPrice = (string) $price->unit_price;
            $lineTotal = Money::multiply($unitPrice, $line->quantity);

            $items[] = [
                'product_id' => $line->productId,
                'warehouse_id' => $line->warehouseId,
                'quantity' => $line->quantity,
                'unit_price' => $unitPrice,
                'moq_applied' => $price->moq,
                'line_total' => $lineTotal,
            ];
            $subtotal = Money::add($subtotal, $lineTotal);
        }

        return [$items, $subtotal];
    }

    private function nextPoNumber(): string
    {
        $sequence = (int) DB::scalar("select nextval('po_number_seq')");

        return sprintf('PO-%s-%06d', now()->format('Y'), $sequence);
    }
}
