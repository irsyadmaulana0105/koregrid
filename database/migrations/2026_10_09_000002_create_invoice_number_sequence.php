<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Sama seperti po_number_seq: aman untuk request paralel, IF NOT EXISTS agar migrate:fresh bisa diulang. */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SEQUENCE IF NOT EXISTS invoice_number_seq START WITH 1 INCREMENT BY 1');
    }

    public function down(): void
    {
        DB::statement('DROP SEQUENCE IF EXISTS invoice_number_seq');
    }
};
