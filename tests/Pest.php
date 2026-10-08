<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
 * PENGAMAN: Feature test menjalankan migrate:fresh (menghapus semua tabel).
 * Test tidak boleh pernah menyentuh database development.
 */
$database = (string) getenv('DB_DATABASE');
if (! str_ends_with($database, '_testing')) {
    fwrite(STDERR, "\nDITOLAK: DB_DATABASE='{$database}' bukan database testing (harus berakhiran _testing).\n");
    exit(1);
}

// Unit dan Architecture sengaja tanpa framework: cepat dan murni.
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
