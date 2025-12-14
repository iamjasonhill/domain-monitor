<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// Domain routes (protected)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/domains', \App\Livewire\DomainsList::class)->name('domains.index');
    Route::get('/domains/create', \App\Livewire\DomainForm::class)->name('domains.create');
    Route::get('/domains/{domain}', \App\Livewire\DomainDetail::class)->name('domains.show');
    Route::get('/domains/{domain}/edit', \App\Livewire\DomainForm::class)->name('domains.edit');
    Route::get('/health-checks', \App\Livewire\HealthChecksList::class)->name('health-checks.index');
});

require __DIR__.'/auth.php';
