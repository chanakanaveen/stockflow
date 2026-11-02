<?php

namespace Tests\Concerns;

use App\Enums\DocumentType;
use App\Models\Product;
use App\Models\StockDocument;
use App\Models\Store;
use App\Models\User;
use App\Services\Inventory\DocumentWriter;
use App\Services\Inventory\PostingService;
use Database\Seeders\RolePermissionSeeder;

trait InventoryFixtures
{
    protected User $manager;

    protected Store $store;

    protected Product $product;

    protected function inventoryFixtures(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->manager = User::factory()->create();
        $this->manager->assignRole('Inventory Manager');
        $this->store = Store::factory()->create();
        $this->manager->stores()->attach($this->store);
        $this->product = Product::factory()->create();
    }

    /** @param list<array{product_id:int,quantity:string}>|null $lines */
    protected function draft(DocumentType $type, string $quantity = '1', ?array $lines = null, ?Store $store = null): StockDocument
    {
        return app(DocumentWriter::class)->create($this->manager, $type, ['store_id' => ($store ?? $this->store)->id, 'lines' => $lines ?? [['product_id' => $this->product->id, 'quantity' => $quantity]]]);
    }

    protected function receive(string $quantity = '10', ?Product $product = null): StockDocument
    {
        $document = $this->draft(DocumentType::Receipt, $quantity, [['product_id' => ($product ?? $this->product)->id, 'quantity' => $quantity]]);
        app(PostingService::class)->post($this->manager, $document, 'opening-'.$document->id);

        return $document->refresh();
    }
}
