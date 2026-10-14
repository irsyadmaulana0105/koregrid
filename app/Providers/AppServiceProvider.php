<?php

namespace App\Providers;

use App\Domain\Audit\Contracts\AuditRecorder;
use App\Domain\Identity\Enums\RoleName;
use App\Domain\Identity\Models\User;
use App\Infrastructure\Audit\DatabaseAuditRecorder;
use Illuminate\Support\Facades\Gate;
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
    }
}
