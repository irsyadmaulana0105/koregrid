<?php

namespace App\Domain\Shared\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Model punya primary key integer (`id`) untuk relasi internal
 * dan kolom `uuid` untuk identitas publik (URL, API).
 */
trait HasPublicUuid
{
    use HasUuids;

    /** Hanya kolom `uuid` yang diisi otomatis; primary key tetap integer. */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** Route model binding memakai uuid, bukan id berurutan yang mudah ditebak. */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
