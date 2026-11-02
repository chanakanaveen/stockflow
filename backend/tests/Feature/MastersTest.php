<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InventoryFixtures;
use Tests\TestCase;

class MastersTest extends TestCase
{
    use InventoryFixtures,RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixtures();
    }

    private function asAdmin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('System Admin');
        $this->actingAs($admin);
    }

    public function test_master_creation_updates_and_duplicate_constraints(): void
    {
        $this->asAdmin();
        $category = $this->postJson('/api/v1/categories', ['name' => 'Office'])->assertCreated()->assertJsonPath('data.is_active', true)->json('data.id');
        $store = $this->postJson('/api/v1/stores', ['code' => ' branch-2 ', 'name' => 'Branch Two'])->assertCreated()->assertJsonPath('data.code', 'BRANCH-2')->json('data.id');
        $payload = ['sku' => ' item-1 ', 'name' => 'Pen', 'category_id' => $category, 'base_uom' => 'unit', 'reorder_level' => '2.5'];
        $product = $this->postJson('/api/v1/products', $payload)->assertCreated()->assertJsonPath('data.sku', 'ITEM-1')->assertJsonPath('data.reorder_level', '2.5000')->json('data.id');
        $this->postJson('/api/v1/products', $payload)->assertUnprocessable();
        $this->postJson('/api/v1/stores', ['code' => 'branch-2', 'name' => 'Duplicate'])->assertUnprocessable();
        $this->patchJson("/api/v1/products/$product", ['name' => 'Blue pen', 'reorder_level' => '0'])->assertOk()->assertJsonPath('data.reorder_level', '0.0000');
        $this->patchJson("/api/v1/categories/$category", ['is_active' => false])->assertOk();
        $this->patchJson("/api/v1/stores/$store", ['is_active' => false])->assertOk();
        $this->getJson('/api/v1/products?search=Blue')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/products?per_page=101')->assertUnprocessable();
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.created', 'subject_id' => $product]);
    }

    public function test_non_admin_cannot_write_masters_and_inactive_category_cannot_be_assigned(): void
    {
        $this->actingAs($this->manager)->postJson('/api/v1/categories', ['name' => 'Denied'])->assertForbidden();
        $this->postJson('/api/v1/stores', ['code' => 'DENIED', 'name' => 'Denied'])->assertForbidden();
        $this->patchJson('/api/v1/products/'.$this->product->id, ['name' => 'Denied'])->assertForbidden();
        $this->asAdmin();
        $category = Category::factory()->create(['is_active' => false]);
        $this->postJson('/api/v1/products', ['sku' => 'INACTIVE', 'name' => 'Product', 'category_id' => $category->id, 'base_uom' => 'unit'])->assertUnprocessable();
    }

    public function test_uom_and_historical_references_are_protected_after_posting(): void
    {
        $this->receive();
        $this->asAdmin();
        $this->patchJson('/api/v1/products/'.$this->product->id, ['base_uom' => 'box'])->assertConflict()->assertJsonPath('error.code', 'UOM_IMMUTABLE');
        $this->deleteJson('/api/v1/products/'.$this->product->id)->assertStatus(405);
        $this->expectException(QueryException::class);
        Product::whereKey($this->product->id)->delete();
    }

    public function test_database_rejects_negative_balances_and_zero_lines(): void
    {
        $this->receive();
        try {
            DB::table('stock_balances')->update(['quantity' => '-1.0000']);
            $this->fail('Database accepted negative stock');
        } catch (QueryException $exception) {
            $this->assertSame(3819, (int) $exception->errorInfo[1]);
        }
        $document = $this->draft(DocumentType::Receipt);
        try {
            DB::table('stock_document_lines')->where('document_id', $document->id)->update(['quantity' => '0']);
            $this->fail('Database accepted zero line');
        } catch (QueryException $exception) {
            $this->assertSame(3819, (int) $exception->errorInfo[1]);
        }
    }
}
