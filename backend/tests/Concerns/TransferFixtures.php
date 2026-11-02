<?php

namespace Tests\Concerns;

use App\Enums\DocumentType;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\User;
use App\Services\Inventory\PostingService;
use App\Services\Inventory\TransferWriter;

trait TransferFixtures
{
    use InventoryFixtures;

    protected Store $destination;

    protected User $approver;

    protected function transferFixtures(): void
    {
        $this->inventoryFixtures();
        $this->destination = Store::factory()->create();
        $this->manager->stores()->attach($this->destination);
        $this->approver = User::factory()->create();
        $this->approver->assignRole('Inventory Manager');
        $this->approver->stores()->attach([$this->store->id, $this->destination->id]);
    }

    protected function transfer(string $quantity = '1', bool $submitted = true, ?array $lines = null, ?Store $from = null, ?Store $to = null): StockTransfer
    {
        $writer = app(TransferWriter::class);
        $transfer = $writer->create($this->manager, ['from_store_id' => ($from ?? $this->store)->id, 'to_store_id' => ($to ?? $this->destination)->id,
            'lines' => $lines ?? [['product_id' => $this->product->id, 'quantity' => $quantity]]]);

        return $submitted ? $writer->submit($this->manager, $transfer) : $transfer;
    }

    protected function openingAt(Store $store, string $quantity, ?Product $product = null): void
    {
        $document = $this->draft(DocumentType::Receipt, lines: [['product_id' => ($product ?? $this->product)->id, 'quantity' => $quantity]], store: $store);
        app(PostingService::class)->post($this->manager, $document, 'opening-'.$document->id);
    }
}
