<?php

use App\Domain\Identity\Enums\PermissionName;
use App\Domain\Identity\Enums\RoleName;

/**
 * Membaca matriks di docs/ARCHITECTURE.md agar dokumentasi dan kode tidak bisa berbeda diam-diam.
 *
 * @return array<string, array<string, bool>> permission => [role => diizinkan]
 */
function documentedPermissionMatrix(): array
{
    $markdown = (string) file_get_contents(dirname(__DIR__, 2).'/docs/ARCHITECTURE.md');
    preg_match_all('/^\| `([a-z.]+)` \|([^\n]*)\|\s*$/m', $markdown, $rows, PREG_SET_ORDER);

    $matrix = [];
    foreach ($rows as $row) {
        $cells = array_map('trim', explode('|', $row[2]));
        if (count($cells) !== 4) {
            continue; // bukan baris matriks (4 kolom role)
        }

        $matrix[$row[1]] = [
            RoleName::SuperAdmin->value => $cells[0] === '✓',
            RoleName::Finance->value => $cells[1] === '✓',
            RoleName::WarehouseStaff->value => $cells[2] === '✓',
            RoleName::Buyer->value => $cells[3] === '✓',
        ];
    }

    return $matrix;
}

it('documents exactly the permissions defined in the enum', function () {
    $documented = array_keys(documentedPermissionMatrix());
    $defined = array_map(fn (PermissionName $p): string => $p->value, PermissionName::cases());

    expect($documented)->toHaveCount(20)
        ->and($documented)->toEqualCanonicalizing($defined);
});

it('grants each role the permissions documented in ARCHITECTURE.md', function (RoleName $role) {
    $granted = array_map(fn (PermissionName $p): string => $p->value, $role->permissions());
    $documented = array_keys(array_filter(
        documentedPermissionMatrix(),
        fn (array $roles): bool => $roles[$role->value],
    ));

    expect($granted)->toEqualCanonicalizing($documented);
})->with([RoleName::Finance, RoleName::WarehouseStaff, RoleName::Buyer]);

it('gives Super Admin no explicit permissions because access comes from Gate::before', function () {
    $everythingAllowed = array_reduce(
        documentedPermissionMatrix(),
        fn (bool $carry, array $roles): bool => $carry && $roles[RoleName::SuperAdmin->value],
        true,
    );

    expect(RoleName::SuperAdmin->permissions())->toBe([])
        ->and($everythingAllowed)->toBeTrue();
});

it('keeps duties segregated between roles', function (RoleName $role, PermissionName $forbidden) {
    expect($role->permissions())->not->toContain($forbidden);
})->with([
    'buyer cannot approve' => [RoleName::Buyer, PermissionName::PoApprove],
    'buyer cannot reject' => [RoleName::Buyer, PermissionName::PoReject],
    'buyer cannot pack' => [RoleName::Buyer, PermissionName::PoPack],
    'buyer cannot ship' => [RoleName::Buyer, PermissionName::PoShip],
    'finance cannot pack' => [RoleName::Finance, PermissionName::PoPack],
    'finance cannot ship' => [RoleName::Finance, PermissionName::PoShip],
    'finance cannot create orders' => [RoleName::Finance, PermissionName::PoCreate],
    'warehouse cannot approve' => [RoleName::WarehouseStaff, PermissionName::PoApprove],
    'warehouse cannot create orders' => [RoleName::WarehouseStaff, PermissionName::PoCreate],
    'warehouse cannot view audit log' => [RoleName::WarehouseStaff, PermissionName::AuditView],
]);

it('reserves administration permissions for Super Admin only', function (PermissionName $permission) {
    foreach ([RoleName::Finance, RoleName::WarehouseStaff, RoleName::Buyer] as $role) {
        expect($role->permissions())->not->toContain($permission);
    }
})->with([
    PermissionName::ProductManage,
    PermissionName::UserManage,
    PermissionName::CompanyManage,
    PermissionName::RoleManage,
]);
