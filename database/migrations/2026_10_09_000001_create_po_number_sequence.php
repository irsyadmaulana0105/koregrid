<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Nomor PO berasal dari sequence PostgreSQL: aman untuk request paralel (tanpa balapan),
 * dan boleh ada celah angka kalau transaksi dibatalkan.
 * IF NOT EXISTS: migrate:fresh menghapus tabel tetapi tidak menghapus sequence mandiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SEQUENCE IF NOT EXISTS po_number_seq START WITH 1 INCREMENT BY 1');
    }

    public function down(): void
    {
        DB::statement('DROP SEQUENCE IF EXISTS po_number_seq');
    }
};
