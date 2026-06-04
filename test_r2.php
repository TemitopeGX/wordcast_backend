<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $success = Illuminate\Support\Facades\Storage::disk('r2_media')->put('test.txt', 'hello world');
    if ($success) {
        echo "UPLOAD_SUCCESS\n";
    } else {
        echo "UPLOAD_FAILED_NO_EXCEPTION\n";
    }
} catch (\Exception $e) {
    echo "UPLOAD_ERROR: " . $e->getMessage() . "\n";
}
