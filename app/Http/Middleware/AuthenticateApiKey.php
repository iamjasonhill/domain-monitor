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

        // Get allowed API keys from config
        // These are the keys that external services (Brain, Website Ops) use to authenticate
        $allowedKeys = [
            config('services.domain_monitor.brain_api_key'),
            config('services.domain_monitor.ops_api_key'),
        ];

        // Filter out empty values
        $allowedKeys = array_filter($allowedKeys);

        if (empty($allowedKeys)) {
            Log::warning('API key authentication attempted but no API keys configured');

            return response()->json([
                'error' => 'Configuration Error',
                'message' => 'API authentication is not properly configured',
            ], 500);
        }

        // Check if the provided token matches any allowed key
        if (! in_array($token, $allowedKeys, true)) {
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
        ]);

        return $next($request);
    }
}
