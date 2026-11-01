<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = config('database.default');
        if ($connection !== 'mysql' || config('database.connections.mysql.url') || config('database.connections.mysql.database') !== 'stockflow_test' || config('database.connections.mysql.username') !== 'stockflow_test') {
            throw new RuntimeException('Tests require the dedicated stockflow_test MySQL database and stockflow_test user.');
        }

        return $app;
    }
}
