<?php

use App\Models\User;
use App\Services\ReportQueries;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.default') !== 'mysql' || config('database.connections.mysql.url') || config('database.connections.mysql.database') !== 'stockflow_test' || config('database.connections.mysql.username') !== 'stockflow_test') {
    throw new RuntimeException('Measurements require isolated stockflow_test.');
}
$actor = User::query()->where('email', 'manager@e2e.stockflow.test')->firstOrFail();
$queries = app(ReportQueries::class);
$request = Request::create('/', 'GET', ['store_id' => 1, 'product_id' => 1, 'date_from' => '2026-01-01', 'date_to' => '2027-01-01']);
$cases = [
    'low_stock_projection' => $queries->stock($actor, Request::create('/'), true)->orderBy('s.id')->orderBy('p.id')->limit(25),
    'filtered_movements' => $queries->movements($actor, $request)->orderBy('l.store_id')->orderBy('l.product_id')->limit(25),
    'filtered_ledger' => $queries->ledger($actor, $request)->select('l.*')->orderByDesc('l.posted_at')->orderByDesc('l.id')->limit(25),
];
$result = ['database_version' => DB::selectOne('SELECT VERSION() as version')->version, 'rows' => [], 'queries' => []];
foreach (['stores', 'products', 'stock_balances', 'stock_ledger_entries'] as $table) {
    $result['rows'][$table] = DB::table($table)->count();
}
foreach ($cases as $name => $query) {
    $times = [];
    for ($i = 0; $i < 6; $i++) {
        $start = hrtime(true);
        $rows = $query->get();
        $elapsed = (hrtime(true) - $start) / 1e6;
        if ($i > 0) {
            $times[] = $elapsed;
        }
    }
    sort($times);
    $result['queries'][$name] = ['returned_rows' => $rows->count(), 'warm_median_ms' => round($times[2], 3), 'samples_ms' => array_map(fn ($n) => round($n, 3), $times), 'plan' => DB::select('EXPLAIN FORMAT=JSON '.$query->toSql(), $query->getBindings())];
}
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
