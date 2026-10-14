<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * RefreshDatabase yang menolak berjalan di database selain *_testing.
 *
 * Yang diperiksa adalah database yang BENAR-BENAR dipakai koneksi Laravel,
 * bukan sekadar variabel lingkungan, sehingga migrate:fresh tidak akan pernah menyentuh data development.
 */
trait SafeRefreshDatabase
{
    use RefreshDatabase {
        refreshTestDatabase as private refreshWithoutGuard;
    }

    protected function refreshTestDatabase()
    {
        $database = (string) DB::connection()->getDatabaseName();

        if (! str_ends_with($database, '_testing')) {
            throw new RuntimeException(
                "DITOLAK: koneksi test memakai database '{$database}', bukan database testing (harus berakhiran _testing). ".
                'migrate:fresh tidak dijalankan. Periksa phpunit.xml dan environment container.',
            );
        }

        $this->refreshWithoutGuard();
    }
}
