<?php

namespace App\Http\Controllers;

use App\Application\Catalog\Queries\CatalogQuery;
use App\Domain\Identity\Enums\PricingTier;
use App\Domain\Identity\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class CatalogController
{
    public function __invoke(Request $request, CatalogQuery $catalog): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $user->loadMissing('company');

        /** @var PricingTier|null $tier */
        $tier = $user->company?->pricing_tier;

        return Inertia::render('Catalog', ['products' => $catalog($tier)]);
    }
}
