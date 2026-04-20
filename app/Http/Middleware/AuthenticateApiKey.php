<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');

        if (! $authHeader || ! str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Missing or invalid Authorization header',
            ], 401);
        }

        $token = substr($authHeader, 7); // Remove 'Bearer ' prefix

        $allowedKeys = [
            'brain' => config('services.domain_monitor.brain_api_key'),
            'ops' => config('services.domain_monitor.ops_api_key'),
            'fleet_control' => config('services.domain_monitor.fleet_control_api_key'),
            'moveroo_removals' => config('services.domain_monitor.moveroo_removals_api_key'),
        ];

        $allowedKeys = array_filter(
            $allowedKeys,
            static fn (mixed $allowedKey): bool => is_string($allowedKey) && $allowedKey !== ''
        );

        if (empty($allowedKeys)) {
            Log::warning('API key authentication attempted but no API keys configured');

            return response()->json([
                'error' => 'Configuration Error',
                'message' => 'API authentication is not properly configured',
            ], 500);
        }

        $authenticatedClient = null;
        foreach ($allowedKeys as $client => $allowedKey) {
            if (hash_equals($allowedKey, $token)) {
                $authenticatedClient = $client;
                break;
            }
        }

        // Check if the provided token matches any allowed key
        if ($authenticatedClient === null) {
            Log::warning('Invalid API key authentication attempt', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid API key',
            ], 401);
        }

        // Log successful authentication (optional, can be removed in production)
        Log::debug('API key authentication successful', [
            'ip' => $request->ip(),
            'path' => $request->path(),
            'client' => $authenticatedClient,
        ]);

        $request->attributes->set('authenticated_api_client', $authenticatedClient);

        return $next($request);
    }
}
