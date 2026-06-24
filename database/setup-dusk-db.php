<?php

use Illuminate\Contracts\Console\Kernel;

/**
 * Setup script for Dusk testing database.
 * Called once before the Dusk test suite runs.
 *
 * Usage: php database/setup-dusk-db.php
 */

// Bootstrap Laravel with dusk environment
$app = require __DIR__.'/../bootstrap/app.php';
$app->loadEnvironmentFrom('.env.dusk.local');
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

// Create SQLite database file
$dbPath = $app->databasePath('dusk.sqlite');
if (! file_exists($dbPath)) {
    touch($dbPath);
    echo "Created SQLite database at: $dbPath\n";
}

// Run migrations fresh with seeder
$exitCode = $kernel->call('migrate:fresh', [
    '--force' => true,
    '--seed' => true,
    '--seeder' => 'Database\Seeders\RolePermissionSeeder',
]);

echo "Migration exit code: $exitCode\n";

if ($exitCode === 0) {
    echo "Dusk database setup complete.\n";
} else {
    echo "Dusk database setup FAILED.\n";
    exit(1);
}

$kernel->terminate();
