<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireFleetControlApiKey
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->attributes->get('authenticated_api_client') !== 'fleet_control') {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Fleet control authentication is required for this action',
            ], 403);
        }

        return $next($request);
    }
}
