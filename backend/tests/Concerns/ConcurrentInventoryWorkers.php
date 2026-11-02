<?php

namespace Tests\Concerns;

use Symfony\Component\Process\Process;

trait ConcurrentInventoryWorkers
{
    /** Runs committed fixtures through separate PHP processes and database connections. */
    protected function race(array $jobs): array
    {
        $directory = storage_path('framework/testing/concurrency-'.bin2hex(random_bytes(12)));
        mkdir($directory, 0700, true);
        $connection = config('database.connections.mysql');
        $environment = ['APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'DB_CONNECTION' => 'mysql', 'DB_URL' => '',
            'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'], 'DB_DATABASE' => $connection['database'],
            'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'], 'CACHE_STORE' => 'array', 'XDEBUG_MODE' => 'off'];
        $processes = [];
        try {
            foreach ($jobs as $index => $job) {
                $path = $directory.'/'.$index.'.json';
                file_put_contents($path, json_encode($job, JSON_THROW_ON_ERROR));
                $process = new Process([PHP_BINARY, '-d', 'xdebug.log=', base_path('tests/Support/inventory-worker.php'), $path], base_path(), $environment, timeout: 120);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 90;
            do {
                foreach ($processes as $process) {
                    if (! $process->isRunning()) {
                        $this->fail('Worker stopped before barrier: '.$process->getErrorOutput());
                    }
                }
                $ready = count(glob($directory.'/*.ready') ?: []) === count($jobs);
                if (! $ready) {
                    usleep(10000);
                }
            } while (! $ready && microtime(true) < $deadline);
            $this->assertTrue($ready, 'All workers must reach the start barrier.');
            file_put_contents($directory.'/go', 'go');
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), 'Worker failed: '.$process->getErrorOutput());
                $results[] = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            foreach (glob($directory.'/*') ?: [] as $path) {
                unlink($path);
            }
            rmdir($directory);
        }
    }
}
