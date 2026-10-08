<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('tier', 10);
            $table->decimal('unit_price', 14, 2);
            $table->integer('moq');
            $table->timestampsTz();

            $table->unique(['product_id', 'tier']);
        });

        DB::statement("ALTER TABLE product_tiers ADD CONSTRAINT product_tiers_tier_check CHECK (tier IN ('gold', 'silver', 'bronze'))");
        DB::statement('ALTER TABLE product_tiers ADD CONSTRAINT product_tiers_unit_price_check CHECK (unit_price >= 0)');
        DB::statement('ALTER TABLE product_tiers ADD CONSTRAINT product_tiers_moq_check CHECK (moq >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('product_tiers');
    }
};
