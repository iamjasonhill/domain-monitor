<?php

namespace App\Providers;

use App\Services\CommandFleetTechnicalSeoBrowserRenderer;
use App\Services\CommandFleetTechnicalSeoLighthouseRunner;
use App\Services\FleetTechnicalSeoBrowserRenderer;
use App\Services\FleetTechnicalSeoLighthouseRunner;
use App\Services\UnavailableFleetTechnicalSeoBrowserRenderer;
use App\Services\UnavailableFleetTechnicalSeoLighthouseRunner;
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

        $this->app->bind(FleetTechnicalSeoLighthouseRunner::class, function (): FleetTechnicalSeoLighthouseRunner {
            $command = config('services.fleet_technical_seo.lighthouse_command');

            if (is_string($command) && trim($command) !== '') {
                return new CommandFleetTechnicalSeoLighthouseRunner(
                    command: $command,
                    timeoutSeconds: (int) config('services.fleet_technical_seo.lighthouse_timeout', 60),
                );
            }

            return new UnavailableFleetTechnicalSeoLighthouseRunner;
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
