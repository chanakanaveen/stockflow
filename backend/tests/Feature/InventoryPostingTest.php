<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Models\Product;
use App\Models\StockLedgerEntry;
use App\Models\Store;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Inventory\Reconciliation;
use App\Support\Quantity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;
use Tests\Concerns\InventoryFixtures;
use Tests\TestCase;

class InventoryPostingTest extends TestCase
{
    use InventoryFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixtures();
        $this->actingAs($this->manager);
    }

    public function test_receipt_api_has_no_draft_effect_and_retries_do_not_duplicate_stock(): void
    {
        $response = $this->postJson('/api/v1/inventory/receipts', ['store_id' => $this->store->id, 'status' => 'posted', 'posted_by' => 999, 'lines' => [['product_id' => $this->product->id, 'quantity' => '0.1']]])->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.lines.0.quantity', '0.1000');
        $id = $response->json('data.id');
        $this->assertDatabaseCount('stock_ledger_entries', 0);
        $this->assertDatabaseCount('stock_balances', 0);
        $first = $this->postJson("/api/v1/inventory/receipts/$id/post", ['a' => 1, 'b' => 2], ['Idempotency-Key' => 'receipt-1'])->assertOk()->assertJsonPath('data.status', 'posted');
        $again = $this->postJson("/api/v1/inventory/receipts/$id/post", ['b' => 2, 'a' => 1], ['Idempotency-Key' => 'receipt-1'])->assertOk();
        $this->assertEquals($first->json('data'), $again->json('data'));
        $this->assertNotSame($first->json('meta.request_id'), $again->json('meta.request_id'));
        $this->postJson("/api/v1/inventory/receipts/$id/post", ['a' => 2], ['Idempotency-Key' => 'receipt-1'])->assertConflict()->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
        $this->postJson("/api/v1/inventory/receipts/$id/post", [], ['Idempotency-Key' => 'new-key'])->assertConflict()->assertJsonPath('error.code', 'ALREADY_PROCESSED');
        $this->assertDatabaseCount('stock_ledger_entries', 1);
        $this->assertDatabaseCount('idempotency_records', 1);
        $this->assertDatabaseHas('stock_balances', ['quantity' => '0.1000']);
        $this->assertDatabaseHas('stock_ledger_entries', ['actor_id' => $this->manager->id, 'source_id' => $id, 'quantity_delta' => '0.1000']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'receipt.posted', 'actor_id' => $this->manager->id]);
        $this->assertSame(['balance_mismatches' => [], 'document_mismatches' => [], 'invalid_sources' => []], app(Reconciliation::class)->inspect());
    }

    public function test_multi_line_issue_preserves_exact_precision(): void
    {
        $second = Product::factory()->create();
        $this->receive('10.0001');
        $this->receive('3', $second);
        $document = $this->draft(DocumentType::Issue, lines: [['product_id' => $second->id, 'quantity' => '1'], ['product_id' => $this->product->id, 'quantity' => '2.1234']]);
        $this->postJson("/api/v1/inventory/issues/{$document->id}/post", [], ['Idempotency-Key' => 'issue'])->assertOk();
        $this->assertDatabaseHas('stock_balances', ['product_id' => $this->product->id, 'quantity' => '7.8767']);
        $this->assertDatabaseHas('stock_balances', ['product_id' => $second->id, 'quantity' => '2.0000']);
        $this->assertDatabaseHas('stock_ledger_entries', ['source_id' => $document->id, 'product_id' => $this->product->id, 'quantity_delta' => '-2.1234']);
        $this->artisan('inventory:reconcile')->assertSuccessful();
    }

    public function test_insufficient_stock_rolls_back_all_lines_and_same_key_can_retry(): void
    {
        $second = Product::factory()->create();
        $this->receive('5');
        $document = $this->draft(DocumentType::Issue, lines: [['product_id' => $this->product->id, 'quantity' => '2'], ['product_id' => $second->id, 'quantity' => '1']]);
        $ledger = DB::table('stock_ledger_entries')->count();
        $audit = DB::table('audit_logs')->count();
        $this->postJson("/api/v1/inventory/issues/{$document->id}/post", [], ['Idempotency-Key' => 'retry'])->assertUnprocessable()->assertJsonPath('error.code', 'INSUFFICIENT_STOCK');
        $this->assertDatabaseHas('stock_documents', ['id' => $document->id, 'status' => 'draft']);
        $this->assertDatabaseHas('stock_balances', ['product_id' => $this->product->id, 'quantity' => '5.0000']);
        $this->assertDatabaseMissing('stock_balances', ['product_id' => $second->id]);
        $this->assertDatabaseCount('stock_ledger_entries', $ledger);
        $this->assertDatabaseCount('audit_logs', $audit);
        $this->assertDatabaseMissing('idempotency_records', ['idempotency_key' => 'retry']);
        $this->receive('1', $second);
        $this->postJson("/api/v1/inventory/issues/{$document->id}/post", [], ['Idempotency-Key' => 'retry'])->assertOk();
    }

    public function test_exception_after_ledger_writes_restores_every_effect(): void
    {
        $this->receive('5');
        $document = $this->draft(DocumentType::Issue, '2');
        $ledger = DB::table('stock_ledger_entries')->count();
        $audit = DB::table('audit_logs')->count();
        $this->mock(AuditLogger::class)->shouldReceive('record')->once()->andThrow(new RuntimeException('private fault injection'));
        $this->postJson("/api/v1/inventory/issues/{$document->id}/post", [], ['Idempotency-Key' => 'fault'])->assertStatus(500)->assertJsonPath('error.code', 'SERVER_ERROR')->assertDontSee('private fault injection');
        $this->assertDatabaseHas('stock_balances', ['quantity' => '5.0000']);
        $this->assertDatabaseHas('stock_documents', ['id' => $document->id, 'status' => 'draft', 'posted_by' => null]);
        $this->assertDatabaseCount('stock_ledger_entries', $ledger);
        $this->assertDatabaseCount('audit_logs', $audit);
        $this->assertDatabaseMissing('idempotency_records', ['idempotency_key' => 'fault']);
    }

    public function test_inactive_products_and_stores_are_rechecked_on_post(): void
    {
        $document = $this->draft(DocumentType::Receipt);
        $this->product->update(['is_active' => false]);
        $this->postJson("/api/v1/inventory/receipts/{$document->id}/post", [], ['Idempotency-Key' => 'active'])->assertUnprocessable()->assertJsonPath('error.code', 'INACTIVE_PRODUCT');
        $this->product->update(['is_active' => true]);
        $this->store->update(['is_active' => false]);
        $this->postJson("/api/v1/inventory/receipts/{$document->id}/post", [], ['Idempotency-Key' => 'active'])->assertUnprocessable()->assertJsonPath('error.code', 'INACTIVE_STORE');
        $this->assertDatabaseCount('stock_ledger_entries', 0);
    }

    public function test_draft_edit_cancel_and_terminal_immutability(): void
    {
        $document = $this->draft(DocumentType::Receipt, '2');
        $this->patchJson("/api/v1/inventory/receipts/{$document->id}", ['lines' => [['product_id' => $this->product->id, 'quantity' => '4.25']]])->assertOk()->assertJsonPath('data.lines.0.quantity', '4.2500');
        $this->postJson("/api/v1/inventory/receipts/{$document->id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->postJson("/api/v1/inventory/receipts/{$document->id}/post", [], ['Idempotency-Key' => 'cancelled'])->assertConflict();
        $this->patchJson("/api/v1/inventory/receipts/{$document->id}", ['remarks' => 'changed'])->assertConflict();
        $posted = $this->receive('1');
        $this->patchJson("/api/v1/inventory/receipts/{$posted->id}", ['remarks' => 'changed'])->assertConflict();
        $this->postJson("/api/v1/inventory/receipts/{$posted->id}/cancel")->assertConflict();
        $this->getJson("/api/v1/inventory/issues/{$posted->id}")->assertNotFound();
        $this->deleteJson('/api/v1/inventory/ledger/1')->assertNotFound();
    }

    public function test_cross_store_access_is_denied_and_lists_are_scoped(): void
    {
        $other = Store::factory()->create();
        $this->manager->stores()->attach($other);
        $document = $this->draft(DocumentType::Receipt, store: $other);
        $this->manager->stores()->detach($other);
        $this->getJson("/api/v1/inventory/receipts/{$document->id}")->assertNotFound();
        $this->postJson("/api/v1/inventory/receipts/{$document->id}/post", [], ['Idempotency-Key' => 'denied'])->assertNotFound();
        $this->postJson('/api/v1/inventory/receipts', ['store_id' => $other->id, 'lines' => [['product_id' => $this->product->id, 'quantity' => '1']]])->assertNotFound();
        $this->getJson('/api/v1/inventory/receipts?store_id='.$other->id)->assertOk()->assertJsonCount(0, 'data');
        $this->receive();
        $keeper = User::factory()->create();
        $keeper->assignRole('Store Keeper');
        $keeper->stores()->attach($other);
        $this->actingAs($keeper)->getJson('/api/v1/inventory/ledger')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/inventory/balances')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_permissions_and_quantity_validation_are_enforced(): void
    {
        foreach ([1, '0', '-1', '1.00001', '1e3', '100000000000000'] as $quantity) {
            $this->postJson('/api/v1/inventory/receipts', ['store_id' => $this->store->id, 'lines' => [['product_id' => $this->product->id, 'quantity' => $quantity]]])->assertUnprocessable();
        }
        $line = ['product_id' => $this->product->id, 'quantity' => '1'];
        $this->postJson('/api/v1/inventory/receipts', ['store_id' => $this->store->id, 'lines' => [$line, $line]])->assertUnprocessable();
        $this->postJson('/api/v1/inventory/receipts', ['store_id' => $this->store->id, 'lines' => []])->assertUnprocessable();
        $document = $this->draft(DocumentType::Receipt);
        $this->postJson("/api/v1/inventory/receipts/{$document->id}/post")->assertUnprocessable();
        $auditor = User::factory()->create();
        $auditor->assignRole('Auditor');
        $auditor->stores()->attach($this->store);
        $this->actingAs($auditor)->postJson("/api/v1/inventory/receipts/{$document->id}/post", [], ['Idempotency-Key' => 'audit'])->assertForbidden();
        $this->postJson('/api/v1/inventory/issues', ['store_id' => $this->store->id, 'lines' => [$line]])->assertForbidden();
    }

    public function test_overflow_is_rejected_and_ledger_model_is_immutable(): void
    {
        $this->receive(Quantity::MAX);
        $document = $this->draft(DocumentType::Receipt, '0.0001');
        $this->postJson("/api/v1/inventory/receipts/{$document->id}/post", [], ['Idempotency-Key' => 'overflow'])->assertUnprocessable()->assertJsonPath('error.code', 'QUANTITY_OVERFLOW');
        $this->assertDatabaseHas('stock_balances', ['quantity' => Quantity::MAX]);
        $this->assertDatabaseCount('stock_ledger_entries', 1);
        $this->expectException(LogicException::class);
        StockLedgerEntry::firstOrFail()->forceFill(['quantity_delta' => '1.0000'])->save();
    }

    public function test_reconciliation_detects_drift_and_expired_replay_cannot_repost(): void
    {
        $document = $this->receive('4');
        $this->artisan('inventory:reconcile')->assertSuccessful();
        DB::table('stock_balances')->update(['quantity' => '3.0000']);
        $this->artisan('inventory:reconcile')->assertFailed();
        DB::table('idempotency_records')->update(['created_at' => now()->subDays(8)]);
        $this->artisan('inventory:prune-idempotency')->assertSuccessful();
        $this->assertDatabaseCount('idempotency_records', 0);
        $this->postJson("/api/v1/inventory/receipts/{$document->id}/post", [], ['Idempotency-Key' => 'opening-'.$document->id])->assertConflict()->assertJsonPath('error.code', 'ALREADY_PROCESSED');
        $this->assertDatabaseCount('stock_ledger_entries', 1);
    }

    public function test_ledger_date_filters_accept_either_boundary_and_validate_order(): void
    {
        $this->receive('1');
        $today = now('UTC')->format('Y-m-d');
        $this->getJson('/api/v1/inventory/ledger?date_to='.$today)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/inventory/ledger?date_from='.$today)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/inventory/ledger?date_from=2026-11-02&date_to=2026-11-01')->assertUnprocessable();
    }
}
