<?php

namespace Database\Seeders;

use App\Domain\Catalog\Models\Product;
use App\Domain\Identity\Enums\PricingTier;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Models\Company;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Data demo untuk development. Hanya berjalan di environment local/testing.
 * Idempotent: aman dijalankan berulang.
 */
class DemoDataSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'password';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('DemoDataSeeder dilewati: hanya untuk environment local/testing.');

            return;
        }

        $warehouses = $this->seedWarehouses();
        $companies = $this->seedCompanies();
        $this->seedCatalogAndStock($warehouses);
        $this->seedUsers($companies, $warehouses);
    }

    /** @return array<string, Warehouse> */
    private function seedWarehouses(): array
    {
        $rows = [
            'WH-JKT' => ['Gudang Jakarta', 'Jl. Industri Raya No. 1, Cikarang', 'Jakarta'],
            'WH-SBY' => ['Gudang Surabaya', 'Jl. Rungkut Industri No. 8', 'Surabaya'],
            'WH-MDN' => ['Gudang Medan', 'Jl. Gatot Subroto No. 21', 'Medan'],
        ];

        $warehouses = [];
        foreach ($rows as $code => [$name, $address, $city]) {
            $warehouses[$code] = Warehouse::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'address' => $address, 'city' => $city, 'is_active' => true],
            );
        }

        return $warehouses;
    }

    /** @return array<string, Company> */
    private function seedCompanies(): array
    {
        $rows = [
            'CMP-0001' => ['PT Maju Jaya', PricingTier::Gold, '01.234.567.8-901.000'],
            'CMP-0002' => ['CV Sinar Abadi', PricingTier::Silver, '02.345.678.9-012.000'],
            'CMP-0003' => ['UD Berkah Sentosa', PricingTier::Bronze, '03.456.789.0-123.000'],
        ];

        $companies = [];
        foreach ($rows as $code => [$name, $tier, $taxId]) {
            $companies[$code] = Company::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'pricing_tier' => $tier,
                    'tax_id' => $taxId,
                    'billing_address' => "Alamat penagihan {$name}",
                    'is_active' => true,
                ],
            );
        }

        return $companies;
    }

    /** @param array<string, Warehouse> $warehouses */
    private function seedCatalogAndStock(array $warehouses): void
    {
        // [tier, faktor harga terhadap harga dasar, MOQ]
        $tierRules = [
            [PricingTier::Bronze, 1.00, 10],
            [PricingTier::Silver, 0.92, 25],
            [PricingTier::Gold, 0.85, 50],
        ];

        $catalog = [
            ['PKG-BOX-001', 'Kardus Corrugated 40x30x30 cm', 'box', 4500],
            ['PKG-TAP-001', 'Lakban Bening 48mm x 100m', 'roll', 12500],
            ['PKG-WRP-001', 'Stretch Film 500mm x 300m', 'roll', 68000],
            ['PKG-BUB-001', 'Bubble Wrap 1m x 50m', 'roll', 95000],
            ['PLT-WOD-001', 'Pallet Kayu 120x100 cm', 'pcs', 185000],
            ['SAF-GLV-001', 'Sarung Tangan Safety (12 pasang)', 'box', 54000],
            ['SAF-HLM-001', 'Helm Safety', 'pcs', 42000],
            ['LBL-THM-001', 'Label Thermal 100x150 mm', 'roll', 38000],
        ];

        $warehouseList = array_values($warehouses);

        foreach ($catalog as $i => [$sku, $name, $unit, $basePrice]) {
            $product = Product::updateOrCreate(
                ['sku' => $sku],
                ['name' => $name, 'unit' => $unit, 'is_active' => true],
            );

            foreach ($tierRules as [$tier, $factor, $moq]) {
                $product->tiers()->updateOrCreate(
                    ['tier' => $tier->value],
                    ['unit_price' => round($basePrice * $factor, -2), 'moq' => $moq],
                );
            }

            // Stok deterministik; tiap produk ada di minimal 2 dari 3 gudang (untuk demo multi-warehouse).
            foreach ($warehouseList as $w => $warehouse) {
                if (($i + $w) % 4 === 0) {
                    continue;
                }

                Inventory::updateOrCreate(
                    ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
                    ['qty_on_hand' => (($i * 7 + $w * 13) % 5 + 1) * 100],
                );
            }
        }
    }

    /**
     * @param  array<string, Company>  $companies
     * @param  array<string, Warehouse>  $warehouses
     */
    private function seedUsers(array $companies, array $warehouses): void
    {
        // [email, nama, role, kode company, kode warehouse]
        $users = [
            ['super@koregrid.test', 'Super Admin', RoleName::SuperAdmin, null, null],
            ['finance@koregrid.test', 'Finance Officer', RoleName::Finance, null, null],
            ['warehouse.jkt@koregrid.test', 'Staf Gudang Jakarta', RoleName::WarehouseStaff, null, 'WH-JKT'],
            ['warehouse.sby@koregrid.test', 'Staf Gudang Surabaya', RoleName::WarehouseStaff, null, 'WH-SBY'],
            ['buyer.gold@koregrid.test', 'Buyer PT Maju Jaya', RoleName::Buyer, 'CMP-0001', null],
            ['buyer.silver@koregrid.test', 'Buyer CV Sinar Abadi', RoleName::Buyer, 'CMP-0002', null],
            ['buyer.bronze@koregrid.test', 'Buyer UD Berkah Sentosa', RoleName::Buyer, 'CMP-0003', null],
        ];

        foreach ($users as [$email, $name, $role, $companyCode, $warehouseCode]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => self::DEMO_PASSWORD,
                    'company_id' => $companies[$companyCode]->id ?? null,
                    'warehouse_id' => $warehouses[$warehouseCode]->id ?? null,
                    'is_active' => true,
                ],
            );

            $user->forceFill(['email_verified_at' => now()])->save();
            $user->syncRoles($role->value);
        }
    }
}
