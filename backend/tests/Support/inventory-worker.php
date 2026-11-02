<?php

use App\Enums\DocumentType;
use App\Exceptions\InventoryException;
use App\Models\StockDocument;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\Inventory\DocumentWriter;
use App\Services\Inventory\PostingService;
use App\Services\Inventory\TransferPostingService;
use App\Services\Inventory\TransferWriter;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || config('database.connections.mysql.url') || config('database.connections.mysql.database') !== 'stockflow_test' || config('database.connections.mysql.username') !== 'stockflow_test') {
    throw new RuntimeException('Concurrency workers require the isolated test database.');
}
$jobPath = $argv[1];
$job = json_decode(file_get_contents($jobPath), true, 512, JSON_THROW_ON_ERROR);
$actor = User::findOrFail($job['actor']);
file_put_contents($jobPath.'.ready', 'ready');
$deadline = microtime(true) + 90;
while (! file_exists(dirname($jobPath).'/go')) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Concurrency barrier timed out.');
    }
    usleep(10000);
}
try {
    if ($job['action'] === 'approve') {
        $result = app(TransferPostingService::class)->approve($actor, StockTransfer::findOrFail($job['transfer']), $job['key']);
    } elseif ($job['action'] === 'reject') {
        $result = ['status' => app(TransferWriter::class)->reject($actor, StockTransfer::findOrFail($job['transfer']), 'Concurrent rejection')->status->value];
    } elseif ($job['action'] === 'transfer-draft') {
        $result = ['number' => app(TransferWriter::class)->create($actor, $job['data'])->number];
    } elseif ($job['action'] === 'draft') {
        $document = app(DocumentWriter::class)->create($actor, DocumentType::Receipt, $job['data']);
        $result = ['number' => $document->number];
    } else {
        $result = app(PostingService::class)->post($actor, StockDocument::findOrFail($job['document']), $job['key']);
    }
    echo json_encode(['status' => 200, 'result' => $result], JSON_THROW_ON_ERROR);
} catch (InventoryException $exception) {
    echo json_encode(['status' => $exception->status, 'code' => $exception->errorCode], JSON_THROW_ON_ERROR);
}
