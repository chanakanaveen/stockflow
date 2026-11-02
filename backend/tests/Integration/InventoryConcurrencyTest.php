<?php

namespace Tests\Integration;

use App\Enums\DocumentType;
use App\Models\Product;
use App\Models\StockDocument;
use App\Services\Inventory\Reconciliation;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ConcurrentInventoryWorkers;
use Tests\Concerns\InventoryFixtures;
use Tests\TestCase;

class InventoryConcurrencyTest extends TestCase
{
    use ConcurrentInventoryWorkers, DatabaseMigrations, InventoryFixtures;

    public function test_independent_mysql_connections_serialize_stock_and_document_writes(): void
    {
        $this->inventoryFixtures();
        $this->receive('10');
        $first = $this->draft(DocumentType::Issue, '7');
        $second = $this->draft(DocumentType::Issue, '7');
        $results = $this->race([$this->postJob($first, 'issue-a'), $this->postJob($second, 'issue-b')]);
        $statuses = array_column($results, 'status');
        sort($statuses);
        $this->assertSame([200, 422], $statuses);
        $this->assertSame('3.0000', DB::table('stock_balances')->where('product_id', $this->product->id)->value('quantity'));
        $this->assertSame(1, DB::table('stock_ledger_entries')->where('movement_type', 'issue')->count());

        // Both receipts encounter the same absent balance row.
        $newProduct = Product::factory()->create();
        $one = $this->draft(DocumentType::Receipt, lines: [['product_id' => $newProduct->id, 'quantity' => '5']]);
        $two = $this->draft(DocumentType::Receipt, lines: [['product_id' => $newProduct->id, 'quantity' => '7']]);
        $this->assertSame([200, 200], array_column($this->race([$this->postJob($one, 'missing-a'), $this->postJob($two, 'missing-b')]), 'status'));
        $this->assertSame('12.0000', DB::table('stock_balances')->where('product_id', $newProduct->id)->sole()->quantity);

        $replay = $this->draft(DocumentType::Receipt);
        $results = $this->race([$this->postJob($replay, 'same-key'), $this->postJob($replay, 'same-key')]);
        $this->assertSame([200, 200], array_column($results, 'status'));
        $this->assertEquals($results[0]['result'], $results[1]['result']);
        $this->assertSame(1, DB::table('stock_ledger_entries')->where('source_id', $replay->id)->count());

        $differentKeys = $this->draft(DocumentType::Receipt);
        $statuses = array_column($this->race([$this->postJob($differentKeys, 'different-a'), $this->postJob($differentKeys, 'different-b')]), 'status');
        sort($statuses);
        $this->assertSame([200, 409], $statuses);

        $job = ['action' => 'draft', 'actor' => $this->manager->id, 'data' => ['store_id' => $this->store->id, 'lines' => [['product_id' => $this->product->id, 'quantity' => '1']]]];
        $numbers = $this->race([$job, $job]);
        $this->assertSame([200, 200], array_column($numbers, 'status'));
        $this->assertNotSame($numbers[0]['result']['number'], $numbers[1]['result']['number']);

        $lines = [['product_id' => $this->product->id, 'quantity' => '0.1'], ['product_id' => $newProduct->id, 'quantity' => '0.2']];
        $one = $this->draft(DocumentType::Receipt, lines: $lines);
        $two = $this->draft(DocumentType::Receipt, lines: array_reverse($lines));
        $this->assertSame([200, 200], array_column($this->race([$this->postJob($one, 'ordered-a'), $this->postJob($two, 'ordered-b')]), 'status'));
        $this->assertSame(['balance_mismatches' => [], 'document_mismatches' => [], 'transfer_mismatches' => [], 'invalid_sources' => []], app(Reconciliation::class)->inspect());
    }

    private function postJob(StockDocument $document, string $key): array
    {
        return ['action' => 'post', 'actor' => $this->manager->id, 'document' => $document->id, 'key' => $key];
    }
}
