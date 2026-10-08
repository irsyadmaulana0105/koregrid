<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->integer('qty_on_hand')->default(0);
            $table->integer('qty_reserved')->default(0);
            $table->timestampsTz();

            $table->unique(['product_id', 'warehouse_id']);
        });

        // Integritas stok dijaga database, bukan hanya aplikasi.
        DB::statement('ALTER TABLE inventories ADD CONSTRAINT inventories_qty_on_hand_check CHECK (qty_on_hand >= 0)');
        DB::statement('ALTER TABLE inventories ADD CONSTRAINT inventories_qty_reserved_check CHECK (qty_reserved >= 0)');
        DB::statement('ALTER TABLE inventories ADD CONSTRAINT inventories_reserved_lte_on_hand_check CHECK (qty_reserved <= qty_on_hand)');
    }

    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
