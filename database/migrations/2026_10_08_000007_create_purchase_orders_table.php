<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->default(DB::raw('gen_random_uuid()'))->unique();
            $table->string('po_number', 32)->unique();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->string('pricing_tier', 10); // snapshot tier company saat PO dibuat
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->timestampTz('reservation_expires_at')->nullable();
            $table->timestampTz('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('packed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('shipped_at')->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->string('invoice_number', 32)->nullable()->unique();
            $table->timestampTz('invoice_issued_at')->nullable();
            $table->timestampsTz();

            $table->index(['company_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        DB::statement("ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_status_check CHECK (status IN ('draft', 'pending_approval', 'approved', 'packing', 'shipped', 'completed', 'rejected', 'cancelled'))");
        DB::statement("ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_pricing_tier_check CHECK (pricing_tier IN ('gold', 'silver', 'bronze'))");
        DB::statement('ALTER TABLE purchase_orders ADD CONSTRAINT purchase_orders_amounts_check CHECK (subtotal >= 0 AND tax_amount >= 0 AND total_amount >= 0)');

        // Index parsial: hanya draft yang perlu dipindai job expiry reservasi.
        DB::statement("CREATE INDEX purchase_orders_draft_expiry_index ON purchase_orders (reservation_expires_at) WHERE status = 'draft'");
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
