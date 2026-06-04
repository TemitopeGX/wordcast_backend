<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$files = Illuminate\Support\Facades\Storage::disk('r2_media')->allFiles();
echo "FILES IN R2 BUCKET:\n";
print_r($files);
