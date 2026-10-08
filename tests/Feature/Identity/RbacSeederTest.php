<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductTier;
use App\Domain\Identity\Enums\PermissionName;
use App\Domain\Identity\Enums\PricingTier;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Models\Company;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\Inventory;
use App\Domain\Inventory\Models\Warehouse;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

// ── Role dan permission ──

it('creates 4 roles and 20 permissions and can be run repeatedly', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::count())->toBe(4)->and(Permission::count())->toBe(20);
});

it('gives each role exactly the permissions defined in RoleName', function (RoleName $role) {
    $this->seed(RolesAndPermissionsSeeder::class);

    $granted = Role::findByName($role->value)->permissions->pluck('name')->all();

    expect($granted)->toEqualCanonicalizing(
        array_map(fn (PermissionName $p): string => $p->value, $role->permissions()),
    );
})->with(RoleName::cases());

it('authorizes users through their role', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $finance = User::factory()->create()->assignRole(RoleName::Finance->value);
    $buyer = User::factory()->create()->assignRole(RoleName::Buyer->value);
    $roleless = User::factory()->create();

    expect($finance->can('po.approve'))->toBeTrue()
        ->and($finance->can('po.pack'))->toBeFalse()
        ->and($buyer->can('po.create'))->toBeTrue()
        ->and($buyer->can('po.approve'))->toBeFalse()
        ->and($roleless->can('po.view.own'))->toBeFalse();
});

it('lets Super Admin pass every permission check through Gate::before', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create()->assignRole(RoleName::SuperAdmin->value);

    expect($admin->can('po.approve'))->toBeTrue()
        ->and($admin->can('user.manage'))->toBeTrue()
        ->and($admin->can('permission.that.does.not.exist'))->toBeTrue();
});

// ── Data demo ──

it('seeds the demo data and can be run repeatedly', function () {
    $this->seed();
    $this->seed(DemoDataSeeder::class);

    expect(User::count())->toBe(7)
        ->and(Company::count())->toBe(3)
        ->and(Warehouse::count())->toBe(3)
        ->and(Product::count())->toBe(8)
        ->and(ProductTier::count())->toBe(24)
        ->and(Inventory::count())->toBe(18);
});

it('prices higher tiers lower per unit but with a higher MOQ', function () {
    $this->seed();

    Product::with('tiers')->get()->each(function (Product $product) {
        $bronze = $product->tierFor(PricingTier::Bronze);
        $silver = $product->tierFor(PricingTier::Silver);
        $gold = $product->tierFor(PricingTier::Gold);

        expect((float) $gold->unit_price)->toBeLessThan((float) $silver->unit_price)
            ->and((float) $silver->unit_price)->toBeLessThan((float) $bronze->unit_price)
            ->and($gold->moq)->toBeGreaterThan($silver->moq)
            ->and($silver->moq)->toBeGreaterThan($bronze->moq);
    });
});

it('stocks every product in at least two warehouses', function () {
    $this->seed();

    $understocked = Product::withCount('inventories')->get()->filter(fn (Product $p): bool => $p->inventories_count < 2);

    expect($understocked)->toHaveCount(0);
});

it('links demo users to a company or warehouse according to their role', function () {
    $this->seed();

    $gold = User::where('email', 'buyer.gold@koregrid.test')->firstOrFail();
    $staff = User::where('email', 'warehouse.jkt@koregrid.test')->firstOrFail();
    $finance = User::where('email', 'finance@koregrid.test')->firstOrFail();

    expect($gold->hasRole(RoleName::Buyer->value))->toBeTrue()
        ->and($gold->company->pricing_tier)->toBe(PricingTier::Gold)
        ->and($staff->hasRole(RoleName::WarehouseStaff->value))->toBeTrue()
        ->and($staff->warehouse->code)->toBe('WH-JKT')
        ->and($staff->company_id)->toBeNull()
        ->and($finance->company_id)->toBeNull()
        ->and($finance->warehouse_id)->toBeNull();
});

it('stores demo passwords hashed, never in plain text', function () {
    $this->seed();

    $user = User::where('email', 'finance@koregrid.test')->firstOrFail();

    expect($user->password)->not->toBe('password')
        ->and(Hash::check('password', $user->password))->toBeTrue();
});

it('refuses to seed demo data outside local and testing environments', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app()->detectEnvironment(fn (): string => 'production');

    // Dipanggil langsung (bukan lewat db:seed) agar yang diuji adalah pengaman seeder, bukan konfirmasi production artisan.
    app(DemoDataSeeder::class)->run();

    expect(User::count())->toBe(0)->and(Company::count())->toBe(0);
});
