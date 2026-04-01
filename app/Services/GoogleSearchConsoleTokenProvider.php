<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleSearchConsoleTokenProvider
{
    public function accessToken(): string
    {
        $configuredAccessToken = trim((string) config('services.google.search_console.access_token', ''));

        if ($configuredAccessToken !== '') {
            return $configuredAccessToken;
        }

        $refreshToken = trim((string) config('services.google.search_console.refresh_token', ''));
        $clientId = trim((string) config('services.google.search_console.client_id', ''));
        $clientSecret = trim((string) config('services.google.search_console.client_secret', ''));
        $tokenUrl = trim((string) config('services.google.search_console.token_url', 'https://oauth2.googleapis.com/token'));

        if ($refreshToken === '' || $clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Google Search Console credentials are not configured. Set GOOGLE_SEARCH_CONSOLE_ACCESS_TOKEN or the refresh-token credentials.');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(30)
            ->post($tokenUrl, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'Unable to refresh the Google Search Console access token (%s).',
                $response->status()
            ));
        }

        $accessToken = trim((string) $response->json('access_token'));

        if ($accessToken === '') {
            throw new RuntimeException('Google token refresh response did not include an access token.');
        }

        return $accessToken;
    }
}
