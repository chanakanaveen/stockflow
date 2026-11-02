<?php

use App\Services\Inventory\Reconciliation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inventory:reconcile', function () {
    $result = app(Reconciliation::class)->inspect();
    foreach ($result as $check => $rows) {
        $this->line($check.': '.count($rows));
    }

    return array_sum(array_map('count', $result)) === 0 ? 0 : 1;
})->purpose('Read-only verification of inventory balances and document ledger entries');

Artisan::command('inventory:prune-idempotency', function () {
    $count = DB::table('idempotency_records')->where('status', 'completed')->whereNotNull('response_code')->where('created_at', '<', now()->subDays(7))->delete();
    $this->info('Removed '.$count.' expired completed idempotency records.');

    return 0;
})->purpose('Remove completed posting replay records older than seven days');

Schedule::command('inventory:prune-idempotency')->daily()->withoutOverlapping();
