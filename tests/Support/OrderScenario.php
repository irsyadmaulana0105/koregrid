<?php

namespace Tests\Support;

use App\Application\Ordering\Actions\ApprovePurchaseOrder;
use App\Application\Ordering\Actions\CompletePurchaseOrder;
use App\Application\Ordering\Actions\CreateDraftPurchaseOrder;
use App\Application\Ordering\Actions\ShipPurchaseOrder;
use App\Application\Ordering\Actions\StartPackingPurchaseOrder;
use App\Application\Ordering\Actions\SubmitPurchaseOrder;
use App\Application\Ordering\DTOs\OrderLineData;
use App\Domain\Catalog\Models\Product;
use App\Domain\Identity\Enums\PricingTier;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Models\Company;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Ordering\Enums\PoStatus;
use App\Domain\Ordering\Models\PurchaseOrder;

/**
 * Satu PO lengkap dengan semua pelakunya (pembeli, finance, staf gudang), yang bisa dimajukan
 * langkah demi langkah lewat use case sungguhan. Beri $tag berbeda untuk membuat skenario kedua dalam satu test.
 */
final class OrderScenario
{
    private function __construct(
        public readonly Company $company,
        public readonly Warehouse $warehouse,
        public readonly Product $product,
        public readonly Inventory $inventory,
        public readonly User $buyer,
        public readonly User $finance,
        public readonly User $staff,
        public PurchaseOrder $order,
    ) {}

    public static function draft(int $quantity = 50, int $onHand = 200, string $tag = '1'): self
    {
        $company = Fixtures::company(PricingTier::Gold, "F-CMP-{$tag}");
        $warehouse = Fixtures::warehouse("F-WH-{$tag}");
        $product = Fixtures::product("F-SKU-{$tag}");
        $inventory = Fixtures::stock($product, $warehouse, $onHand);
        $buyer = Fixtures::user(RoleName::Buyer, $company);

        $order = app(CreateDraftPurchaseOrder::class)($buyer, [
            new OrderLineData($product->id, $warehouse->id, $quantity),
        ]);

        // Reservasi dilakukan lewat UPDATE SQL, jadi objek di memori harus dimuat ulang agar mencerminkan stok terkunci.
        $inventory->refresh();

        return new self(
            $company,
            $warehouse,
            $product,
            $inventory,
            $buyer,
            Fixtures::user(RoleName::Finance),
            Fixtures::user(RoleName::WarehouseStaff, null, $warehouse),
            $order,
        );
    }

    public function submit(): self
    {
        return $this->refreshed(app(SubmitPurchaseOrder::class)($this->buyer, $this->order));
    }

    public function approve(): self
    {
        return $this->refreshed(app(ApprovePurchaseOrder::class)($this->finance, $this->order));
    }

    public function startPacking(): self
    {
        return $this->refreshed(app(StartPackingPurchaseOrder::class)($this->staff, $this->order));
    }

    public function ship(string $trackingNumber = 'TRK-001'): self
    {
        return $this->refreshed(app(ShipPurchaseOrder::class)($this->staff, $this->order, $trackingNumber));
    }

    public function complete(): self
    {
        return $this->refreshed(app(CompletePurchaseOrder::class)($this->buyer, $this->order));
    }

    /** Memajukan PO lewat jalur normal sampai status tujuan (draft = tidak melakukan apa pun). */
    public function advanceTo(PoStatus $target): self
    {
        $steps = [
            fn () => $this->submit(),
            fn () => $this->approve(),
            fn () => $this->startPacking(),
            fn () => $this->ship(),
            fn () => $this->complete(),
        ];

        foreach ($steps as $step) {
            if ($this->order->status === $target) {
                break;
            }
            $step();
        }

        return $this;
    }

    private function refreshed(PurchaseOrder $order): self
    {
        $this->order = $order->refresh();
        $this->inventory->refresh();

        return $this;
    }
}
