<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('sku', 64)->unique();
            $table->string('name');
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('base_uom', 32);
            $table->decimal('reorder_level', 18, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('stock_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 4)->default(0);
            $table->timestamps();
            $table->unique(['store_id', 'product_id']);
        });
        Schema::create('document_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 16);
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('last_value')->default(0);
            $table->unique(['kind', 'year']);
        });
        Schema::create('stock_documents', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 64)->unique();
            $table->string('type', 16);
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->string('status', 16)->default('draft');
            $table->string('reference')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('posted_at', 6)->nullable();
            $table->timestamps();
            $table->index(['type', 'status', 'created_at']);
        });
        Schema::create('stock_document_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_id')->constrained('stock_documents')->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->timestamps();
            $table->unique(['document_id', 'product_id']);
        });
        Schema::create('stock_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('movement_type', 24);
            $table->string('source_type', 16);
            $table->unsignedBigInteger('source_id');
            $table->unsignedBigInteger('source_line_id');
            $table->decimal('quantity_delta', 18, 4);
            $table->decimal('balance_after', 18, 4);
            $table->timestamp('posted_at', 6);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at', 6);
            $table->unique(['source_type', 'source_line_id', 'movement_type'], 'ledger_source_movement_unique');
            $table->index(['store_id', 'product_id', 'posted_at', 'id'], 'ledger_store_product_posted_index');
        });
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 64);
            $table->string('subject_type', 64);
            $table->unsignedBigInteger('subject_id');
            $table->uuid('request_id')->nullable();
            $table->json('metadata');
            $table->timestamp('created_at', 6);
            $table->index(['subject_type', 'subject_id', 'created_at'], 'audit_subject_created_index');
        });
        Schema::create('idempotency_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('route_key', 128)->charset('ascii')->collation('ascii_bin');
            $table->string('idempotency_key', 128)->charset('ascii')->collation('ascii_bin');
            $table->char('request_hash', 64);
            $table->string('status', 16)->default('completed');
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->json('response_body')->nullable();
            $table->string('resource_type', 32)->nullable();
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->timestamps();
            $table->unique(['actor_id', 'route_key', 'idempotency_key'], 'idempotency_actor_route_key_unique');
            $table->index('created_at');
        });
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_reorder_nonnegative CHECK (reorder_level >= 0)');
        DB::statement('ALTER TABLE stock_balances ADD CONSTRAINT balances_nonnegative CHECK (quantity >= 0)');
        DB::statement('ALTER TABLE stock_document_lines ADD CONSTRAINT lines_quantity_positive CHECK (quantity > 0)');
        DB::statement("ALTER TABLE stock_documents ADD CONSTRAINT document_type_valid CHECK (type IN ('receipt','issue')), ADD CONSTRAINT document_status_valid CHECK (status IN ('draft','posted','cancelled'))");
        DB::statement("ALTER TABLE stock_ledger_entries ADD CONSTRAINT ledger_balance_nonnegative CHECK (balance_after >= 0), ADD CONSTRAINT ledger_direction_valid CHECK ((movement_type IN ('receipt','transfer_in') AND quantity_delta > 0) OR (movement_type IN ('issue','transfer_out') AND quantity_delta < 0)), ADD CONSTRAINT ledger_source_valid CHECK (source_type IN ('document','transfer'))");
    }

    public function down(): void
    {
        foreach (['idempotency_records', 'audit_logs', 'stock_ledger_entries', 'stock_document_lines', 'stock_documents', 'document_sequences', 'stock_balances', 'products', 'categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
