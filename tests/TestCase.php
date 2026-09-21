<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Safety net: RefreshDatabase wipes the database it runs on. Refuse to
     * run against anything that is not an in-memory SQLite DB or a MySQL
     * database whose name ends in "_test" (never the real crm_assurance).
     */
    protected function beforeRefreshingDatabase()
    {
        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if ($database !== ':memory:' && ! str_ends_with($database, '_test')) {
            throw new RuntimeException("Refusing to reset database \"{$database}\": tests must use a *_test database.");
        }
    }
}
