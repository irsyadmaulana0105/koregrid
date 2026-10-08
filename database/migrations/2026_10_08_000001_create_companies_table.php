<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->default(DB::raw('gen_random_uuid()'))->unique();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('tax_id', 30)->nullable();
            $table->text('billing_address')->nullable();
            $table->string('pricing_tier', 10)->default('bronze');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();
        });

        DB::statement("ALTER TABLE companies ADD CONSTRAINT companies_pricing_tier_check CHECK (pricing_tier IN ('gold', 'silver', 'bronze'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
