<?php

namespace App\Providers;

use App\Services\BrainEventClient;
use Illuminate\Support\ServiceProvider;

class BrainServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(BrainEventClient::class, function ($app) {
            $baseUrl = config('services.brain.base_url');
            $apiKey = config('services.brain.api_key');

            if (empty($baseUrl) || empty($apiKey)) {
                // Return a null client if not configured (prevents errors in development)
                return new BrainEventClient('', '');
            }

            return new BrainEventClient($baseUrl, $apiKey);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
