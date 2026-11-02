<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Inventory\Reconciliation;
use App\Services\Inventory\TransferWriter;
use App\Support\Quantity;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\Concerns\TransferFixtures;
use Tests\TestCase;

class TransferTest extends TestCase
{
    use RefreshDatabase, TransferFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transferFixtures();
        $this->actingAs($this->manager);
    }

    private function approve(StockTransfer $transfer, string $key = 'approve', array $body = []): TestResponse
    {
        return $this->actingAs($this->approver)->postJson('/api/v1/transfers/'.$transfer->id.'/approve', $body, ['Idempotency-Key' => $key]);
    }

    public function test_acceptance_example_posts_once_with_exact_conservation(): void
    {
        $this->openingAt($this->store, '100');
        $this->openingAt($this->destination, '30');
        $draft = $this->postJson('/api/v1/transfers', ['from_store_id' => $this->store->id, 'to_store_id' => $this->destination->id,
            'status' => 'approved_posted', 'approved_by' => $this->manager->id, 'lines' => [['product_id' => $this->product->id, 'quantity' => '20']]])->assertCreated()->assertJsonPath('data.status', 'draft');
        $transfer = StockTransfer::findOrFail($draft->json('data.id'));
        $this->assertStringStartsWith('TRF-'.now('UTC')->year.'-', $transfer->number);
        $this->postJson('/api/v1/transfers/'.$transfer->id.'/submit')->assertOk()->assertJsonPath('data.status', 'submitted');
        $this->assertDatabaseCount('stock_ledger_entries', 2);
        $first = $this->approve($transfer, body: ['a' => 1, 'b' => 2])->assertOk()->assertJsonPath('data.status', 'approved_posted')->assertJsonPath('data.approved_by', $this->approver->id);
        $again = $this->approve($transfer, body: ['b' => 2, 'a' => 1])->assertOk();
        $this->assertEquals($first->json('data'), $again->json('data'));
        $this->assertNotSame($first->json('meta.request_id'), $again->json('meta.request_id'));
        $this->approve($transfer, body: ['a' => 2])->assertConflict()->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
        $this->approve($transfer, 'new-key')->assertConflict()->assertJsonPath('error.code', 'ALREADY_PROCESSED');
        $this->assertDatabaseHas('stock_balances', ['store_id' => $this->store->id, 'quantity' => '80.0000']);
        $this->assertDatabaseHas('stock_balances', ['store_id' => $this->destination->id, 'quantity' => '50.0000']);
        $this->assertDatabaseCount('stock_ledger_entries', 4);
        $this->assertDatabaseHas('stock_ledger_entries', ['source_type' => 'transfer', 'source_id' => $transfer->id, 'movement_type' => 'transfer_out', 'quantity_delta' => '-20.0000']);
        $this->assertDatabaseHas('stock_ledger_entries', ['source_type' => 'transfer', 'source_id' => $transfer->id, 'movement_type' => 'transfer_in', 'quantity_delta' => '20.0000']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'transfer.approved_posted', 'actor_id' => $this->approver->id]);
        $this->artisan('inventory:reconcile')->assertSuccessful();
    }

    public function test_creator_and_distinct_submitter_cannot_approve_even_as_admin(): void
    {
        $this->manager->syncRoles(['System Admin']);
        $draft = $this->transfer(submitted: false);
        app(TransferWriter::class)->submit($this->approver, $draft);
        $this->postJson('/api/v1/transfers/'.$draft->id.'/approve', [], ['Idempotency-Key' => 'creator'])->assertForbidden();
        $this->approve($draft)->assertForbidden();
        $this->assertDatabaseCount('stock_ledger_entries', 0);
        $this->assertDatabaseCount('idempotency_records', 0);
    }

    public function test_both_store_scope_applies_to_all_actions_lists_and_replays(): void
    {
        $this->receive('5');
        $draft = $this->transfer(submitted: false);
        $submitted = $this->transfer();
        $posted = $this->transfer();
        $this->approve($posted)->assertOk();
        $this->approver->stores()->detach($this->destination);
        $this->approve($posted)->assertNotFound();
        $this->actingAs($this->manager);
        $this->manager->stores()->detach($this->destination);
        $this->getJson('/api/v1/transfers')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/transfers/'.$submitted->id)->assertNotFound();
        foreach (['submit', 'cancel', 'approve', 'reject'] as $action) {
            $this->postJson('/api/v1/transfers/'.$draft->id.'/'.$action, ['rejection_reason' => 'Denied'], ['Idempotency-Key' => 'denied'])->assertNotFound();
        }
        $this->patchJson('/api/v1/transfers/'.$draft->id, ['lines' => [['product_id' => $this->product->id, 'quantity' => '2']]])->assertNotFound();
        $this->postJson('/api/v1/transfers', ['from_store_id' => $this->store->id, 'to_store_id' => $this->destination->id, 'lines' => [['product_id' => $this->product->id, 'quantity' => '1']]])->assertNotFound();
    }

    public function test_approval_inbox_is_eligible_scoped_and_paginated(): void
    {
        $submitted = $this->transfer();
        $this->transfer(submitted: false);
        $own = app(TransferWriter::class)->create($this->approver, ['from_store_id' => $this->store->id, 'to_store_id' => $this->destination->id, 'lines' => [['product_id' => $this->product->id, 'quantity' => '1']]]);
        app(TransferWriter::class)->submit($this->manager, $own);
        $this->actingAs($this->approver)->getJson('/api/v1/transfers?approval_inbox=1&per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $submitted->id)->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/transfers?per_page=101')->assertUnprocessable();
        $this->getJson('/api/v1/transfers?date_to='.now('UTC')->format('Y-m-d'))->assertOk()->assertJsonCount(3, 'data');
        $this->approver->stores()->detach($this->store);
        $this->getJson('/api/v1/transfers?approval_inbox=1')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_submitted_and_terminal_transfers_are_immutable(): void
    {
        $draft = $this->transfer(submitted: false);
        $this->patchJson('/api/v1/transfers/'.$draft->id, ['lines' => [['product_id' => $this->product->id, 'quantity' => '2.1234']]])->assertOk()->assertJsonPath('data.lines.0.quantity', '2.1234');
        $this->postJson('/api/v1/transfers/'.$draft->id.'/submit')->assertOk();
        $this->patchJson('/api/v1/transfers/'.$draft->id, ['from_store_id' => $this->destination->id])->assertConflict();
        foreach (['submit', 'cancel'] as $action) {
            $this->postJson('/api/v1/transfers/'.$draft->id.'/'.$action)->assertConflict();
        }
        $this->actingAs($this->approver)->postJson('/api/v1/transfers/'.$draft->id.'/reject', ['rejection_reason' => '  Needs correction  '])->assertOk()->assertJsonPath('data.rejection_reason', 'Needs correction');
        $this->assertDatabaseHas('stock_transfers', ['id' => $draft->id, 'rejected_by' => $this->approver->id]);
        foreach (['submit', 'cancel', 'reject', 'approve'] as $action) {
            $this->postJson('/api/v1/transfers/'.$draft->id.'/'.$action, ['rejection_reason' => 'Again'], ['Idempotency-Key' => 'terminal'])->assertConflict();
        }
        $this->patchJson('/api/v1/transfers/'.$draft->id, ['to_store_id' => $this->store->id])->assertConflict();
        $this->assertDatabaseCount('stock_ledger_entries', 0);
        $cancelled = $this->transfer(submitted: false);
        $this->postJson('/api/v1/transfers/'.$cancelled->id.'/cancel')->assertOk();
        $this->postJson('/api/v1/transfers/'.$cancelled->id.'/submit')->assertConflict();
        $this->approve($cancelled)->assertConflict();
        $unsubmitted = $this->transfer(submitted: false);
        $this->approve($unsubmitted)->assertConflict();
        $this->postJson('/api/v1/transfers/'.$unsubmitted->id.'/reject', ['rejection_reason' => 'Not submitted'])->assertConflict();
    }

    public function test_rejection_requires_reason_and_keepers_and_auditors_cannot_approve(): void
    {
        $transfer = $this->transfer();
        foreach (['', ' ', str_repeat('x', 1001)] as $reason) {
            $this->actingAs($this->approver)->postJson('/api/v1/transfers/'.$transfer->id.'/reject', ['rejection_reason' => $reason])->assertUnprocessable();
        }
        foreach (['Store Keeper', 'Auditor'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);
            $user->stores()->attach([$this->store->id, $this->destination->id]);
            $this->actingAs($user)->postJson('/api/v1/transfers/'.$transfer->id.'/approve', [], ['Idempotency-Key' => 'denied'])->assertForbidden();
            $this->postJson('/api/v1/transfers/'.$transfer->id.'/reject', ['rejection_reason' => 'No'])->assertForbidden();
            $this->getJson('/api/v1/transfers?approval_inbox=1')->assertForbidden();
            $this->getJson('/api/v1/transfers/'.$transfer->id)->assertOk();
        }
    }

    public function test_validation_and_active_masters_are_rechecked(): void
    {
        $data = ['from_store_id' => $this->store->id, 'to_store_id' => $this->store->id, 'lines' => [['product_id' => $this->product->id, 'quantity' => '1']]];
        $this->postJson('/api/v1/transfers', $data)->assertUnprocessable();
        $data['to_store_id'] = $this->destination->id;
        foreach ([1, '0', '-1', '1.00001', '1e2', '100000000000000'] as $quantity) {
            $data['lines'][0]['quantity'] = $quantity;
            $this->postJson('/api/v1/transfers', $data)->assertUnprocessable();
        }
        $draft = $this->transfer(submitted: false);
        $this->destination->update(['is_active' => false]);
        $this->postJson('/api/v1/transfers/'.$draft->id.'/submit')->assertUnprocessable();
        $this->destination->update(['is_active' => true]);
        $this->postJson('/api/v1/transfers/'.$draft->id.'/submit')->assertOk();
        $this->product->update(['is_active' => false]);
        $this->approve($draft)->assertUnprocessable()->assertJsonPath('error.code', 'INACTIVE_PRODUCT');
        $this->product->update(['is_active' => true]);
        $this->destination->update(['is_active' => false]);
        $this->approve($draft)->assertUnprocessable()->assertJsonPath('error.code', 'INACTIVE_STORE');
        $this->assertDatabaseCount('stock_ledger_entries', 0);
    }

    public function test_insufficient_multi_line_stock_rolls_back_and_same_key_can_retry(): void
    {
        $second = Product::factory()->create();
        $this->receive('10');
        $transfer = $this->transfer(lines: [['product_id' => $this->product->id, 'quantity' => '2'], ['product_id' => $second->id, 'quantity' => '1']]);
        $audit = DB::table('audit_logs')->count();
        $this->approve($transfer)->assertUnprocessable()->assertJsonPath('error.code', 'INSUFFICIENT_STOCK');
        $this->assertDatabaseHas('stock_balances', ['store_id' => $this->store->id, 'quantity' => '10.0000']);
        $this->assertDatabaseMissing('stock_balances', ['store_id' => $this->destination->id]);
        $this->assertDatabaseHas('stock_transfers', ['id' => $transfer->id, 'status' => 'submitted', 'approved_by' => null]);
        $this->assertDatabaseCount('stock_ledger_entries', 1);
        $this->assertDatabaseCount('audit_logs', $audit);
        $this->assertDatabaseMissing('idempotency_records', ['resource_type' => 'stock_transfer']);
        $this->receive('1', $second);
        $this->approve($transfer)->assertOk();
        $this->artisan('inventory:reconcile')->assertSuccessful();
    }

    public function test_destination_overflow_and_injected_failure_roll_back_all_effects(): void
    {
        $this->receive('10');
        $this->openingAt($this->destination, Quantity::MAX);
        $overflow = $this->transfer();
        $this->approve($overflow)->assertUnprocessable()->assertJsonPath('error.code', 'QUANTITY_OVERFLOW');
        $second = Product::factory()->create();
        $this->receive('3', $second);
        $transfer = $this->transfer(lines: [['product_id' => $second->id, 'quantity' => '2']]);
        $before = DB::table('audit_logs')->count();
        $this->mock(AuditLogger::class)->shouldReceive('record')->once()->andThrow(new RuntimeException('private transfer fault'));
        $this->approve($transfer)->assertStatus(500)->assertDontSee('private transfer fault');
        $this->assertDatabaseHas('stock_balances', ['store_id' => $this->store->id, 'product_id' => $second->id, 'quantity' => '3.0000']);
        $this->assertDatabaseMissing('stock_balances', ['store_id' => $this->destination->id, 'product_id' => $second->id]);
        $this->assertDatabaseCount('stock_ledger_entries', 3);
        $this->assertDatabaseCount('audit_logs', $before);
        $this->assertDatabaseHas('stock_transfers', ['id' => $transfer->id, 'status' => 'submitted', 'approved_by' => null, 'posted_at' => null]);
        $this->assertDatabaseMissing('idempotency_records', ['resource_type' => 'stock_transfer']);
    }

    public function test_posted_immutability_expiry_and_transfer_reconciliation(): void
    {
        $this->receive('4');
        $transfer = $this->transfer('2');
        $this->approve($transfer)->assertOk();
        foreach (['submit', 'cancel', 'reject'] as $action) {
            $this->postJson('/api/v1/transfers/'.$transfer->id.'/'.$action, ['rejection_reason' => 'Again'])->assertConflict();
        }
        $this->patchJson('/api/v1/transfers/'.$transfer->id, ['lines' => [['product_id' => $this->product->id, 'quantity' => '1']]])->assertConflict();
        DB::table('idempotency_records')->update(['created_at' => now()->subDays(8)]);
        $this->artisan('inventory:prune-idempotency')->assertSuccessful();
        $this->approve($transfer)->assertConflict()->assertJsonPath('error.code', 'ALREADY_PROCESSED');
        DB::table('stock_ledger_entries')->where('source_type', 'transfer')->where('movement_type', 'transfer_in')->update(['quantity_delta' => '1.0000']);
        $result = app(Reconciliation::class)->inspect();
        $this->assertCount(1, $result['transfer_mismatches']);
        $this->artisan('inventory:reconcile')->assertFailed();
    }

    public function test_missing_idempotency_key_and_inactive_actor_are_denied(): void
    {
        $transfer = $this->transfer();
        $this->actingAs($this->approver)->postJson('/api/v1/transfers/'.$transfer->id.'/approve')->assertUnprocessable();
        $this->approver->forceFill(['is_active' => false])->save();
        $this->approve($transfer)->assertForbidden();
        $this->assertDatabaseCount('idempotency_records', 0);
    }

    public function test_keeper_can_draft_submit_and_cancel_but_auditor_cannot_mutate(): void
    {
        $this->manager->syncRoles(['Store Keeper']);
        $data = ['from_store_id' => $this->store->id, 'to_store_id' => $this->destination->id, 'lines' => [['product_id' => $this->product->id, 'quantity' => '1']]];
        $id = $this->postJson('/api/v1/transfers', $data)->assertCreated()->json('data.id');
        $this->patchJson('/api/v1/transfers/'.$id, ['lines' => [['product_id' => $this->product->id, 'quantity' => '2']]])->assertOk();
        $this->postJson('/api/v1/transfers/'.$id.'/submit')->assertOk();
        $cancelId = $this->postJson('/api/v1/transfers', $data)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/transfers/'.$cancelId.'/cancel')->assertOk();
        $this->manager->syncRoles(['Auditor']);
        $this->postJson('/api/v1/transfers', $data)->assertForbidden();
        $this->patchJson('/api/v1/transfers/'.$id, $data)->assertForbidden();
        $this->assertDatabaseCount('stock_ledger_entries', 0);
    }

    public function test_database_constraints_protect_distinct_stores_and_no_self_approval(): void
    {
        $transfer = $this->transfer();
        foreach ([['to_store_id' => $this->store->id], ['approved_by' => $this->manager->id]] as $change) {
            try {
                DB::table('stock_transfers')->where('id', $transfer->id)->update($change);
                $this->fail('Database accepted an invalid transfer.');
            } catch (QueryException $exception) {
                $this->assertSame(3819, (int) $exception->errorInfo[1]);
            }
        }
    }
}
