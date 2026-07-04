<?php

namespace Tests;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $this->ensureSqliteDatabaseExists();

        parent::setUp();

        // Seed roles & permissions once per test so factory states work
        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * Ensure the SQLite database file exists before RefreshDatabase tries to migrate.
     *
     * SQLiteConnector requires the file to exist; artisan('migrate:fresh') won't
     * auto-create it, causing "Database file does not exist" for all tests.
     */
    private function ensureSqliteDatabaseExists(): void
    {
        $database = getenv('DB_DATABASE') ?: ':memory:';
        $connection = getenv('DB_CONNECTION') ?: 'sqlite';

        if ($connection !== 'sqlite' || $database === ':memory:') {
            return;
        }

        // Resolve relative paths against the project root
        if (! str_starts_with($database, '/')) {
            $database = dirname(__DIR__) . '/' . $database;
        }

        if (! file_exists($database)) {
            $dir = dirname($database);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            touch($database);
        }
    }
}
