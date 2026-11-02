<?php

namespace Tests\Integration;

use App\Enums\DocumentType;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\User;
use App\Services\Inventory\Reconciliation;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ConcurrentInventoryWorkers;
use Tests\Concerns\TransferFixtures;
use Tests\TestCase;

class TransferConcurrencyTest extends TestCase
{
    use ConcurrentInventoryWorkers, DatabaseMigrations, TransferFixtures;

    public function test_transfer_races_preserve_conservation_uniqueness_and_terminal_state(): void
    {
        $this->transferFixtures();
        $this->receive('10');
        $first = $this->transfer('7');
        $second = $this->transfer('7');
        $this->assertStatuses([200, 422], $this->race([$this->job($first, 'compete-a'), $this->job($second, 'compete-b')]));
        $this->assertDatabaseHas('stock_balances', ['store_id' => $this->store->id, 'quantity' => '3.0000']);
        $this->assertDatabaseHas('stock_balances', ['store_id' => $this->destination->id, 'quantity' => '7.0000']);
        $this->assertSame(2, DB::table('stock_ledger_entries')->where('source_type', 'transfer')->count());

        $sameKey = $this->transfer('0.1');
        $results = $this->race([$this->job($sameKey, 'same'), $this->job($sameKey, 'same')]);
        $this->assertStatuses([200, 200], $results);
        $this->assertEquals($results[0]['result'], $results[1]['result']);
        $this->assertSame(2, DB::table('stock_ledger_entries')->where('source_type', 'transfer')->where('source_id', $sameKey->id)->count());

        $differentKeys = $this->transfer('0.1');
        $secondApprover = User::factory()->create();
        $secondApprover->assignRole('Inventory Manager');
        $secondApprover->stores()->attach([$this->store->id, $this->destination->id]);
        $otherJob = $this->job($differentKeys, 'different-b');
        $otherJob['actor'] = $secondApprover->id;
        $this->assertStatuses([200, 409], $this->race([$this->job($differentKeys, 'different-a'), $otherJob]));

        // Opposite directions with reversed product input order must share the same balance lock order.
        $product = Product::factory()->create();
        $this->openingAt($this->store, '5', $product);
        $this->openingAt($this->destination, '5', $product);
        $lines = [['product_id' => $this->product->id, 'quantity' => '0.2'], ['product_id' => $product->id, 'quantity' => '0.3']];
        $out = $this->transfer(lines: $lines);
        $in = $this->transfer(lines: array_reverse($lines), from: $this->destination, to: $this->store);
        $before = DB::table('stock_balances')->orderBy('id')->pluck('quantity', 'id')->all();
        $this->assertStatuses([200, 200], $this->race([$this->job($out, 'out'), $this->job($in, 'in')]));
        $this->assertSame($before, DB::table('stock_balances')->orderBy('id')->pluck('quantity', 'id')->all());

        // Two approvals concurrently initialize the same missing destination balance.
        $newStore = Store::factory()->create();
        $this->manager->stores()->attach($newStore);
        $this->approver->stores()->attach($newStore);
        $one = $this->transfer('0.1', to: $newStore);
        $two = $this->transfer('0.2', to: $newStore);
        $this->assertStatuses([200, 200], $this->race([$this->job($one, 'missing-a'), $this->job($two, 'missing-b')]));
        $this->assertSame('0.3000', DB::table('stock_balances')->where('store_id', $newStore->id)->sole()->quantity);

        // Transfers and ordinary issues serialize against the same source balance.
        $fresh = Product::factory()->create();
        $this->receive('10', $fresh);
        $issue = $this->draft(DocumentType::Issue, lines: [['product_id' => $fresh->id, 'quantity' => '7']]);
        $transfer = $this->transfer(lines: [['product_id' => $fresh->id, 'quantity' => '7']]);
        $this->assertStatuses([200, 422], $this->race([$this->job($transfer, 'cross-type'), ['action' => 'post', 'actor' => $this->manager->id, 'document' => $issue->id, 'key' => 'cross-type']]));
        $this->assertDatabaseHas('stock_balances', ['store_id' => $this->store->id, 'product_id' => $fresh->id, 'quantity' => '3.0000']);

        $decision = $this->transfer('0.1');
        $this->assertStatuses([200, 409], $this->race([$this->job($decision, 'decision'), ['action' => 'reject', 'actor' => $this->approver->id, 'transfer' => $decision->id]]));
        $decision->refresh();
        $expected = $decision->status->value === 'approved_posted' ? 2 : 0;
        $this->assertSame($expected, DB::table('stock_ledger_entries')->where('source_type', 'transfer')->where('source_id', $decision->id)->count());

        $draftJob = ['action' => 'transfer-draft', 'actor' => $this->manager->id, 'data' => ['from_store_id' => $this->store->id, 'to_store_id' => $this->destination->id, 'lines' => [['product_id' => $this->product->id, 'quantity' => '1']]]];
        $numbers = $this->race([$draftJob, $draftJob]);
        $this->assertStatuses([200, 200], $numbers);
        $this->assertNotSame($numbers[0]['result']['number'], $numbers[1]['result']['number']);
        $this->assertSame(['balance_mismatches' => [], 'document_mismatches' => [], 'transfer_mismatches' => [], 'invalid_sources' => []], app(Reconciliation::class)->inspect());
    }

    private function job(StockTransfer $transfer, string $key): array
    {
        return ['action' => 'approve', 'actor' => $this->approver->id, 'transfer' => $transfer->id, 'key' => $key];
    }

    private function assertStatuses(array $expected, array $results): void
    {
        $actual = array_column($results, 'status');
        sort($actual);
        $this->assertSame($expected, $actual);
    }
}
