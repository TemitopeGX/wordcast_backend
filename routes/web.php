<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// A convenient way to run migrations on shared hosting without SSH access
Route::get('/migrate', function (\Illuminate\Http\Request $request) {
    if ($request->query('secret') !== 'wordcast2026') {
        abort(403, 'Unauthorized action.');
    }
    
    \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    return 'Migrations completed successfully!';
});
