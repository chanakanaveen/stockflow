<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Inventory\TransferPostingService;
use App\Services\SafeCsv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\TransferFixtures;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase, TransferFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transferFixtures();
        $this->actingAs($this->manager);
    }

    public function test_zero_stock_projection_is_scoped_active_and_paginated(): void
    {
        $this->product->update(['reorder_level' => '2.0000']);
        $hidden = Store::factory()->create();
        Product::factory()->create(['is_active' => false]);
        $this->getJson('/api/v1/reports/low-stock')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.quantity', '0.0000');
        $this->receive('3.1250');
        $this->getJson('/api/v1/reports/stock-summary?per_page=1')->assertJsonPath('meta.total', 2)->assertJsonPath('meta.last_page', 2)->assertJsonPath('data.0.quantity', '3.1250');
        $this->getJson('/api/v1/reports/stock-summary?store_id='.$hidden->id)->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/dashboard/summary')->assertJsonPath('data.active_stores', 2)->assertJsonPath('data.low_stock_positions', 1);
        $this->destination->update(['is_active' => false]);
        $this->getJson('/api/v1/reports/low-stock')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/reports/stock-summary?per_page=101')->assertUnprocessable();
    }

    public function test_movement_aggregates_match_ledger_and_transfer_conservation_with_utc_bounds(): void
    {
        $this->receive('100.0001');
        $transfer = $this->transfer('20.0001');
        app(TransferPostingService::class)->approve($this->approver, $transfer, 'report-transfer');
        $data = $this->getJson('/api/v1/reports/movements')->assertOk()->json('data');
        $this->assertSame('100.0001', $data[0]['incoming']);
        $this->assertSame('20.0001', $data[0]['outgoing']);
        $this->assertSame('80.0000', $data[0]['net']);
        $this->assertSame('20.0001', $data[1]['net']);
        $this->assertSame('100.0001', bcadd($data[0]['net'], $data[1]['net'], 4));
        DB::table('stock_ledger_entries')->update(['posted_at' => '2026-01-31 23:59:59']);
        $this->getJson('/api/v1/reports/movements?date_from=2026-01-31&date_to=2026-01-31')->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/reports/movements?date_from=2026-02-01')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/reports/movements?date_from=2026-02-02&date_to=2026-02-01')->assertUnprocessable();
        $this->manager->stores()->sync([$this->store->id]);
        $this->getJson('/api/v1/reports/movements')->assertJsonCount(1, 'data');
    }

    public function test_csv_is_scoped_quoted_formula_safe_bounded_and_permission_checked(): void
    {
        $this->product->update(['name' => '=HYPERLINK("https://example.invalid")', 'sku' => '@SUM(1,2)']);
        $this->receive('1.2500');
        DB::table('stock_ledger_entries')->update(['posted_at' => '2026-01-01 12:00:00']);
        $url = '/api/v1/reports/movements/export?date_from=2026-01-01&date_to=2026-01-01';
        $response = $this->get($url)->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $rows = array_map(fn ($line) => str_getcsv($line, ',', '"', ''), explode("\r\n", trim($csv)));
        $this->assertCount(11, $rows[1]);
        $this->assertSame("'@SUM(1,2)", $rows[1][3]);
        $this->assertSame("'=HYPERLINK(\"https://example.invalid\")", $rows[1][4]);
        $this->assertSame('1.2500', $rows[1][7]);
        foreach (["\t=1", "\r=1", "\n=1", ' =1', '+1', '-1', '＝1', '＠x'] as $value) {
            $this->assertSame("'".$value, SafeCsv::text($value));
        }
        $this->get('/api/v1/reports/movements/export')->assertUnprocessable();
        $this->get('/api/v1/reports/movements/export?date_from=2020-01-01&date_to=2026-01-01')->assertUnprocessable();
        $keeper = User::factory()->create();
        $keeper->assignRole('Store Keeper');
        $keeper->stores()->attach($this->store);
        $this->actingAs($keeper)->get($url)->assertForbidden();
        $auditor = User::factory()->create();
        $auditor->assignRole('Auditor');
        $this->actingAs($auditor)->get($url)->assertOk();
        $this->assertSame(1, substr_count(trim($this->get($url)->streamedContent()), "\n") + 1);
    }

    public function test_audit_requires_permission_and_both_transfer_stores_and_hides_identity_events(): void
    {
        $receipt = $this->receive();
        $transfer = $this->transfer();
        app(AuditLogger::class)->record('user.updated', $this->manager, $this->manager, ['role' => 'Inventory Manager']);
        $this->getJson('/api/v1/audit-logs')->assertForbidden();
        $auditor = User::factory()->create();
        $auditor->assignRole('Auditor');
        $auditor->stores()->attach($this->store);
        $rows = $this->actingAs($auditor)->getJson('/api/v1/audit-logs')->assertOk()->json('data');
        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertSame('stock_documents', $row['subject_type']);
            $this->assertSame($receipt->id, $row['subject_id']);
        }
        $auditor->stores()->attach($this->destination);
        $this->getJson('/api/v1/audit-logs?action=transfer.submit')->assertJsonCount(1, 'data')->assertJsonPath('data.0.subject_id', $transfer->id);
        $admin = User::factory()->create();
        $admin->assignRole('System Admin');
        $this->actingAs($admin)->getJson('/api/v1/audit-logs?action=user.updated')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/audit-logs?per_page=101')->assertUnprocessable();
    }
}
