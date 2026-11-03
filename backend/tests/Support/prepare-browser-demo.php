<?php

use App\Enums\DocumentType;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\Inventory\DocumentWriter;
use App\Services\Inventory\PostingService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.default') !== 'mysql' || config('database.connections.mysql.url') || config('database.connections.mysql.database') !== 'stockflow_test' || config('database.connections.mysql.username') !== 'stockflow_test') {
    throw new RuntimeException('Browser demo requires the isolated stockflow_test database and user.');
}
$password = getenv('E2E_PASSWORD');
if (! $password || strlen($password) < 16) {
    throw new RuntimeException('Provide a generated E2E_PASSWORD through the process environment.');
}
Artisan::call('migrate:fresh', ['--force' => true]);
Artisan::call('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true]);
$stores = [];
foreach (['Main Warehouse', 'Downtown Branch', 'Lakeside Branch'] as $i => $name) {
    $stores[] = Store::create(['code' => ['MAIN', 'DOWN', 'LAKE'][$i], 'name' => $name, 'location_label' => ['Colombo', 'City center', 'Lakeside'][$i], 'is_active' => true]);
}
$users = [];
foreach (['admin' => 'System Admin', 'manager' => 'Inventory Manager', 'approver' => 'Inventory Manager', 'keeper' => 'Store Keeper', 'auditor' => 'Auditor'] as $key => $role) {
    $user = User::create(['name' => ['admin' => 'Alex Morgan', 'manager' => 'Sam Perera', 'approver' => 'Jamie Silva', 'keeper' => 'Casey Reed', 'auditor' => 'Taylor Chen'][$key], 'email' => $key.'@e2e.stockflow.test', 'password' => $password]);
    $user->assignRole($role);
    $user->stores()->sync($key === 'keeper' ? [$stores[0]->id] : array_map(fn ($s) => $s->id, $stores));
    $users[$key] = $user;
}
$categories = [];
foreach (['Office supplies', 'Electronics', 'Accessories'] as $name) {
    $categories[] = Category::create(['name' => $name, 'is_active' => true]);
}
$names = ['Desk notebook', 'Wireless mouse', 'USB-C adapter', 'Document folder', 'Desk lamp', 'Laptop stand', 'Gel pen set', 'Compact keyboard', 'Cable organizer', 'Sticky notes', 'Portable charger', 'Travel pouch', 'Weekly planner', 'Webcam', 'Screen cloth', 'Binder clips', 'Headphones', 'Desk mat', 'Index cards', 'USB hub', 'Phone stand', 'Writing pad', 'Monitor light', 'Carry case'];
$products = [];
foreach ($names as $i => $name) {
    $products[] = Product::create(['sku' => 'SF-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT), 'name' => $name, 'category_id' => $categories[$i % 3]->id, 'base_uom' => 'unit', 'reorder_level' => '10.0000', 'is_active' => true]);
}
foreach ($stores as $i => $store) {
    $document = app(DocumentWriter::class)->create($users['manager'], DocumentType::Receipt, ['store_id' => $store->id, 'reference' => 'Fictional browser demo opening', 'lines' => array_map(fn ($p) => ['product_id' => $p->id, 'quantity' => ['100.0000', '30.0000', '5.0000'][$i]], $products)]);
    app(PostingService::class)->post($users['manager'], $document, 'browser-opening-'.$document->id);
}
echo "Prepared isolated fictional browser data: 3 stores, 24 products, 5 users.\n";
