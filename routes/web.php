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
    
    \Illuminate\Support\Facades\Artisan::call('migrate:fresh', ['--force' => true]);
    return 'Migrations completed successfully!';
});

Route::get('/wipe', function (\Illuminate\Http\Request $request) {
    if ($request->query('secret') !== 'wordcast2026') {
        abort(403, 'Unauthorized action.');
    }
    
    \Illuminate\Support\Facades\Artisan::call('db:wipe', ['--force' => true]);
    return 'All tables dropped successfully! Database is empty.';
});
