<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Catalog\Models\Product;
use App\Domain\Identity\Models\Company;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Ordering\Enums\PoStatus;
use App\Domain\Ordering\Models\PurchaseOrder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Assert;

/**
 * Pernyataan harus DITOLAK database dengan SQLSTATE tertentu.
 * Dibungkus transaksi bersarang (savepoint) supaya transaksi test tidak rusak setelah error PostgreSQL.
 */
function expectDbRejection(Closure $statement, string $sqlState): void
{
    try {
        DB::transaction($statement);
    } catch (QueryException $e) {
        expect((string) ($e->getPrevious()?->getCode() ?: $e->getCode()))->toBe($sqlState);

        return;
    }

    Assert::fail('Pernyataan seharusnya ditolak database, tetapi berhasil.');
}

function dbTestCompany(): Company
{
    return Company::create(['code' => 'T-CMP', 'name' => 'Test Co', 'pricing_tier' => 'gold']);
}

function dbTestBuyer(Company $company): User
{
    return User::factory()->create(['company_id' => $company->id]);
}

function dbTestOrder(?Company $company = null, string $poNumber = 'T-PO-1'): PurchaseOrder
{
    $company ??= dbTestCompany();
    $buyer = dbTestBuyer($company);

    return PurchaseOrder::create([
        'po_number' => $poNumber,
        'company_id' => $company->id,
        'buyer_id' => $buyer->id,
        'pricing_tier' => 'gold',
    ]);
}

function dbTestProduct(string $sku = 'T-SKU'): Product
{
    return Product::create(['sku' => $sku, 'name' => 'Test Product', 'unit' => 'pcs']);
}

function dbTestWarehouse(string $code = 'T-WH'): Warehouse
{
    return Warehouse::create(['code' => $code, 'name' => 'Test Warehouse']);
}

// ── Companies dan users ──

it('rejects an unknown pricing tier on companies', function () {
    expectDbRejection(fn () => DB::table('companies')->insert([
        'code' => 'X1', 'name' => 'X', 'pricing_tier' => 'platinum',
    ]), '23514');
});

it('rejects emails that differ only by letter case, even when the model is bypassed', function () {
    User::factory()->create(['email' => 'tester@example.com']);

    expectDbRejection(fn () => DB::table('users')->insert([
        'name' => 'Duplicate', 'email' => 'TESTER@EXAMPLE.COM', 'password' => 'x',
    ]), '23505');
});

it('refuses to delete a company that still has users', function () {
    $company = dbTestCompany();
    dbTestBuyer($company);

    expectDbRejection(fn () => DB::table('companies')->where('id', $company->id)->delete(), '23503');
});

// ── Katalog dan harga ──

it('rejects invalid product tier rows', function (array $row) {
    $product = dbTestProduct();

    expectDbRejection(fn () => DB::table('product_tiers')->insert($row + ['product_id' => $product->id]), '23514');
})->with([
    'unknown tier' => [['tier' => 'platinum', 'unit_price' => 1, 'moq' => 1]],
    'negative price' => [['tier' => 'gold', 'unit_price' => -1, 'moq' => 1]],
    'zero MOQ' => [['tier' => 'gold', 'unit_price' => 1, 'moq' => 0]],
]);

it('allows only one price row per product and tier', function () {
    $product = dbTestProduct();
    $product->tiers()->create(['tier' => 'gold', 'unit_price' => 100000, 'moq' => 5]);

    expectDbRejection(fn () => $product->tiers()->create(['tier' => 'gold', 'unit_price' => 1, 'moq' => 1]), '23505');
});

// ── Stok ──

it('rejects reserved stock above physical stock', function () {
    $inventory = Inventory::create([
        'product_id' => dbTestProduct()->id,
        'warehouse_id' => dbTestWarehouse()->id,
        'qty_on_hand' => 10,
    ]);

    expectDbRejection(fn () => DB::table('inventories')->where('id', $inventory->id)->update(['qty_reserved' => 11]), '23514');
});

it('rejects negative physical stock', function () {
    $inventory = Inventory::create([
        'product_id' => dbTestProduct()->id,
        'warehouse_id' => dbTestWarehouse()->id,
        'qty_on_hand' => 10,
    ]);

    expectDbRejection(fn () => DB::table('inventories')->where('id', $inventory->id)->update(['qty_on_hand' => -1]), '23514');
});

it('reserves stock atomically and never oversells', function () {
    $product = dbTestProduct();
    $warehouse = dbTestWarehouse();
    $inventory = Inventory::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'qty_on_hand' => 10,
    ]);

    // Pernyataan reservasi persis seperti di docs/DATA-MODEL.md.
    $reserve = fn (int $qty): int => DB::update(
        'UPDATE inventories SET qty_reserved = qty_reserved + ? WHERE product_id = ? AND warehouse_id = ? AND qty_on_hand - qty_reserved >= ?',
        [$qty, $product->id, $warehouse->id, $qty],
    );

    expect($reserve(4))->toBe(1)      // sisa 6
        ->and($reserve(7))->toBe(0)   // 7 > sisa 6, ditolak tanpa error
        ->and($reserve(6))->toBe(1)   // sisa 0
        ->and($reserve(1))->toBe(0);  // stok habis

    $inventory->refresh();
    expect($inventory->qty_reserved)->toBe(10)->and($inventory->available)->toBe(0);
});

// ── Purchase order ──

it('accepts every PoStatus value in the status column', function (PoStatus $status) {
    $order = dbTestOrder();

    DB::table('purchase_orders')->where('id', $order->id)->update(['status' => $status->value]);

    expect(DB::table('purchase_orders')->where('id', $order->id)->value('status'))->toBe($status->value);
})->with(PoStatus::cases());

it('rejects an unknown PO status', function () {
    $order = dbTestOrder();

    expectDbRejection(fn () => DB::table('purchase_orders')->where('id', $order->id)->update(['status' => 'unknown']), '23514');
});

it('rejects negative PO amounts', function () {
    $order = dbTestOrder();

    expectDbRejection(fn () => DB::table('purchase_orders')->where('id', $order->id)->update(['total_amount' => -1]), '23514');
});

it('rejects a duplicate PO number', function () {
    $first = dbTestOrder();

    expectDbRejection(fn () => PurchaseOrder::create([
        'po_number' => 'T-PO-1',
        'company_id' => $first->company_id,
        'buyer_id' => $first->buyer_id,
        'pricing_tier' => 'gold',
    ]), '23505');
});

it('rejects PO items with zero quantity', function () {
    $order = dbTestOrder();

    expectDbRejection(fn () => $order->items()->create([
        'product_id' => dbTestProduct()->id,
        'warehouse_id' => dbTestWarehouse()->id,
        'quantity' => 0,
        'unit_price' => 100000,
        'moq_applied' => 5,
        'line_total' => 0,
    ]), '23514');
});

it('rejects the same product and warehouse twice on one PO', function () {
    $order = dbTestOrder();
    $item = [
        'product_id' => dbTestProduct()->id,
        'warehouse_id' => dbTestWarehouse()->id,
        'quantity' => 5,
        'unit_price' => 100000,
        'moq_applied' => 5,
        'line_total' => 500000,
    ];
    $order->items()->create($item);

    expectDbRejection(fn () => $order->items()->create($item), '23505');
});

// ── Audit log append-only ──

function dbTestAuditLog(): AuditLog
{
    return AuditLog::create([
        'event' => 'created',
        'auditable_type' => 'test',
        'auditable_id' => 1,
        'new_values' => ['status' => 'draft'],
    ]);
}

it('appends audit rows but the database refuses to change or remove them', function () {
    $log = dbTestAuditLog();

    expect($log->exists)->toBeTrue();
    expectDbRejection(fn () => DB::table('audit_logs')->where('id', $log->id)->update(['event' => 'tampered']), '23001');
    expectDbRejection(fn () => DB::table('audit_logs')->where('id', $log->id)->delete(), '23001');
    expectDbRejection(fn () => DB::statement('TRUNCATE audit_logs'), '23001');
});

it('also refuses to update or delete audit logs through the model', function () {
    $log = dbTestAuditLog();

    expect(fn () => $log->update(['event' => 'tampered']))->toThrow(LogicException::class);
    expect(fn () => $log->delete())->toThrow(LogicException::class);
});
