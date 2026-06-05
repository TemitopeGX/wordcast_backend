<?php

if (!isset($_GET['secret']) || $_GET['secret'] !== 'wordcast2026') {
    http_response_code(403);
    die('Unauthorized');
}

require __DIR__.'/../vendor/autoload.php';

// Boot the Laravel Application
$app = require_once __DIR__.'/../bootstrap/app.php';

// Grab the Console Kernel (bypassing HTTP Kernel and Sessions!)
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

// Run the migrations
$kernel->call('migrate:fresh', ['--force' => true]);

echo "Migrations completed perfectly! You can now delete this file.";
