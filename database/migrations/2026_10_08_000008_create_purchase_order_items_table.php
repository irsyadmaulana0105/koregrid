<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_price', 14, 2);   // snapshot harga tier
            $table->integer('moq_applied');         // snapshot MOQ
            $table->decimal('line_total', 14, 2);
            $table->timestampsTz();

            // Index unik ini sekaligus melayani pencarian per purchase_order_id.
            $table->unique(['purchase_order_id', 'product_id', 'warehouse_id'], 'poi_po_product_warehouse_unique');
            $table->index(['product_id', 'warehouse_id']);
        });

        DB::statement('ALTER TABLE purchase_order_items ADD CONSTRAINT purchase_order_items_quantity_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE purchase_order_items ADD CONSTRAINT purchase_order_items_unit_price_check CHECK (unit_price >= 0)');
        DB::statement('ALTER TABLE purchase_order_items ADD CONSTRAINT purchase_order_items_moq_applied_check CHECK (moq_applied >= 1)');
        DB::statement('ALTER TABLE purchase_order_items ADD CONSTRAINT purchase_order_items_line_total_check CHECK (line_total >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
