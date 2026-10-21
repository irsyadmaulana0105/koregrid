<?php

namespace App\Http\Controllers;

use App\Application\Ordering\Queries\VisibleOrdersQuery;
use App\Domain\Identity\Models\User;
use App\Domain\Ordering\Enums\PoStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController
{
    public function __invoke(Request $request, VisibleOrdersQuery $visibleOrders): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $counts = $visibleOrders($user)
            ->reorder()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $stats = [];
        foreach (PoStatus::cases() as $status) {
            $stats[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return Inertia::render('Dashboard', ['stats' => $stats]);
    }
}
