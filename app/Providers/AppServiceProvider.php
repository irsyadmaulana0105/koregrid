<?php

namespace App\Providers;

use App\Domain\Audit\Contracts\AuditRecorder;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Models\User;
use App\Domain\Ordering\Models\PurchaseOrder;
use App\Infrastructure\Audit\DatabaseAuditRecorder;
use App\Policies\PurchaseOrderPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuditRecorder::class, DatabaseAuditRecorder::class);
    }

    public function boot(): void
    {
        // Super Admin lolos semua pengecekan permission tanpa menyalin permission ke role-nya.
        // Mengembalikan null (bukan false) agar pengecekan permission biasa tetap berjalan untuk role lain.
        Gate::before(fn (User $user, string $ability) => $user->hasRole(RoleName::SuperAdmin->value) ? true : null);

        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);

        // Batas umum untuk semua halaman yang butuh login: per user, atau per IP jika belum login.
        RateLimiter::for('web', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->getAuthIdentifier() ?: $request->ip()));
    }
}
