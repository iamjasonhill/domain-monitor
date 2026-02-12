<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        parent::boot();

        // Horizon::routeSmsNotificationsTo('15556667777');
        // Horizon::routeMailNotificationsTo('example@example.com');
        // Horizon::routeSlackNotificationsTo('slack-webhook-url', '#channel');
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', function ($user = null) {
            if (! $user) {
                return false;
            }

            if (config('app.env') === 'local') {
                return true;
            }

            $allowedEmails = array_filter(
                array_map(
                    static fn ($email): string => mb_strtolower(trim((string) $email)),
                    (array) config('horizon.allowed_emails', [])
                )
            );

            if ($allowedEmails === []) {
                return false;
            }

            return in_array(mb_strtolower((string) $user->email), $allowedEmails, true);
        });
    }
}
