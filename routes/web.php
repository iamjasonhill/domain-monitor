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

    Route::get('/web-properties', function () {
        return view('web-properties.index');
    })->name('web-properties.index');

    Route::get('/fleet-properties', function () {
        return view('web-properties.fleet');
    })->name('web-properties.fleet');

    Route::get('/matomo-coverage', function () {
        return view('matomo-coverage.index');
    })->name('matomo-coverage.index');

    Route::get('/automation-coverage', function () {
        return view('automation-coverage.index');
    })->name('automation-coverage.index');

    Route::get('/manual-csv-backlog', function () {
        return view('manual-csv-backlog.index');
    })->name('manual-csv-backlog.index');

    Route::get('/search-console-coverage', function () {
        return view('search-console-coverage.index');
    })->name('search-console-coverage.index');

    Route::get('/web-properties/{propertySlug}', function (string $propertySlug) {
        return view('web-properties.show', ['propertySlug' => $propertySlug]);
    })->name('web-properties.show');

    Route::get('/domains/{domain}', function (string $domain) {
        return view('domains.show', ['domainId' => $domain]);
    })->name('domains.show');

    Route::get('/domains/{domain}/edit', function (string $domain) {
        return view('domains.edit', ['domainId' => $domain]);
    })->name('domains.edit');

    Route::get('/health-checks', function () {
        return view('health-checks.index');
    })->name('health-checks.index');

    Route::get('/eligibility-checks', function () {
        return view('eligibility-checks.index');
    })->name('eligibility-checks.index');

    Route::get('/alerts', function () {
        return view('alerts.index');
    })->name('alerts.index');

    Route::get('/hosting', \App\Livewire\HostingReliability::class)->name('hosting.index');

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

    Route::get('/settings/monitoring', function () {
        return view('settings.monitoring');
    })->name('settings.monitoring');
});

require __DIR__.'/auth.php';
