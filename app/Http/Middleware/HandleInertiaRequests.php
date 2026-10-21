<?php

namespace App\Http\Middleware;

use App\Domain\Identity\Enums\PermissionName;
use App\Domain\Identity\Enums\PricingTier;
use App\Domain\Identity\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * Data yang dibagikan ke semua halaman. Hanya field yang aman ditampilkan, bukan model mentah.
     * Peta `can` hanya untuk menampilkan atau menyembunyikan tombol; server tetap menegakkan hak akses di setiap aksi.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => ['user' => $user instanceof User ? $this->userProps($user) : null],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function userProps(User $user): array
    {
        $user->loadMissing(['company', 'warehouse']);

        $can = [];
        foreach (PermissionName::cases() as $permission) {
            $can[$permission->value] = $user->can($permission->value);
        }

        /** @var PricingTier|null $tier */
        $tier = $user->company?->pricing_tier;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames()->values()->all(),
            'company' => $user->company === null ? null : [
                'name' => $user->company->name,
                'pricing_tier' => $tier?->value,
            ],
            'warehouse' => $user->warehouse === null ? null : ['name' => $user->warehouse->name],
            'can' => $can,
        ];
    }
}
