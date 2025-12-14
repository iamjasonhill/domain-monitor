<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$migrations = \Illuminate\Support\Facades\DB::table('migrations')->get();
echo "Migrations that have been run:\n";
foreach ($migrations as $migration) {
    echo "  - {$migration->migration}\n";
}

$tables = \Illuminate\Support\Facades\DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
echo "\nTables in database:\n";
foreach ($tables as $table) {
    echo "  - {$table->tablename}\n";
}
