<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }

    return view('welcome');
});

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// Domain routes (protected)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/domains', function () {
        return view('domains.index');
    })->name('domains.index');

    Route::get('/domains/create', function () {
        return view('domains.create');
    })->name('domains.create');

    Route::get('/domains/{domain}', function (string $domain) {
        return view('domains.show', ['domainId' => $domain]);
    })->name('domains.show');

    Route::get('/domains/{domain}/edit', function (string $domain) {
        return view('domains.edit', ['domainId' => $domain]);
    })->name('domains.edit');

    Route::get('/health-checks', function () {
        return view('health-checks.index');
    })->name('health-checks.index');

    // Settings routes
    Route::get('/settings', function () {
        return view('settings.index');
    })->name('settings.index');

    Route::get('/settings/commands', function () {
        return view('settings.commands');
    })->name('settings.commands');

    Route::get('/settings/scheduled-tasks', function () {
        return view('settings.scheduled-tasks');
    })->name('settings.scheduled-tasks');

    Route::get('/settings/tags', function () {
        return view('settings.tags');
    })->name('settings.tags');
});

require __DIR__.'/auth.php';
