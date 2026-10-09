<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * PENGAMAN: RefreshDatabase menjalankan migrate:fresh (menghapus semua tabel).
     * Diperiksa SEBELUM aplikasi dan trait test diinisialisasi, supaya database development tidak pernah tersentuh.
     */
    protected function setUp(): void
    {
        $database = (string) getenv('DB_DATABASE');

        if (! str_ends_with($database, '_testing')) {
            throw new RuntimeException(
                "DITOLAK: DB_DATABASE='{$database}' bukan database testing (harus berakhiran _testing). Periksa phpunit.xml.",
            );
        }

        parent::setUp();
    }
}
