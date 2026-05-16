<?php

namespace App\Providers;

use App\Services\CommandFleetTechnicalSeoBrowserRenderer;
use App\Services\FleetTechnicalSeoBrowserRenderer;
use App\Services\UnavailableFleetTechnicalSeoBrowserRenderer;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FleetTechnicalSeoBrowserRenderer::class, function (): FleetTechnicalSeoBrowserRenderer {
            $command = config('services.fleet_technical_seo.browser_render_command');

            if (is_string($command) && trim($command) !== '') {
                return new CommandFleetTechnicalSeoBrowserRenderer(
                    command: $command,
                    timeoutSeconds: (int) config('services.fleet_technical_seo.browser_render_timeout', 20),
                );
            }

            return new UnavailableFleetTechnicalSeoBrowserRenderer;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
