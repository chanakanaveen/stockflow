<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 64)->unique();
            $table->foreignId('from_store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('to_store_id')->constrained('stores')->restrictOnDelete();
            $table->string('status', 24)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at', 6)->nullable();
            $table->timestamp('approved_at', 6)->nullable();
            $table->timestamp('posted_at', 6)->nullable();
            $table->timestamp('rejected_at', 6)->nullable();
            $table->string('rejection_reason', 1000)->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
        Schema::create('stock_transfer_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transfer_id')->constrained('stock_transfers')->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->timestamps();
            $table->unique(['transfer_id', 'product_id']);
        });
        DB::statement("ALTER TABLE stock_transfers ADD CONSTRAINT transfer_stores_different CHECK (from_store_id <> to_store_id), ADD CONSTRAINT transfer_status_valid CHECK (status IN ('draft','submitted','approved_posted','rejected','cancelled')), ADD CONSTRAINT transfer_no_self_approval CHECK (approved_by IS NULL OR (approved_by <> created_by AND submitted_by IS NOT NULL AND approved_by <> submitted_by))");
        DB::statement('ALTER TABLE stock_transfer_lines ADD CONSTRAINT transfer_line_positive CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_lines');
        Schema::dropIfExists('stock_transfers');
    }
};
