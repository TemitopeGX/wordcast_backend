<?php

use Illuminate\Support\Facades\Route;
use App\Models\Waitlist;
use App\Models\User;
use App\Mail\WaitlistConfirmation;
use App\Mail\WaitlistApproved;
use App\Mail\WaitlistWelcome;

Route::get('/clear', function () {
    \Illuminate\Support\Facades\Artisan::call('optimize:clear');
    return 'Cache cleared successfully!';
});

Route::get('/run-migrations', function () {
    \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    return 'Migrations run successfully!';
});

Route::get('/preview/waitlist-confirmation', function () {
    $entry = new Waitlist(['name' => 'John Doe', 'email' => 'john@example.com']);
    return new WaitlistConfirmation($entry);
});

Route::get('/preview/waitlist-approved', function () {
    $entry = new Waitlist(['name' => 'John Doe', 'email' => 'john@example.com']);
    return new WaitlistApproved($entry, 'dummy-token-123');
});

Route::get('/preview/waitlist-welcome', function () {
    $user = new User(['name' => 'John Doe', 'email' => 'john@example.com']);
    return new WaitlistWelcome($user, 'WCL-BETA-ABCD-1234-WXYZ');
});
