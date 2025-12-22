<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Minimal Brain Event Client for Laravel
 *
 * Copy this class into your Laravel application to send events to Brain Nucleus.
 *
 * Usage:
 *   $client = new BrainEventClient('https://your-brain-instance.com', 'your-api-key');
 *   $client->send('user.signup', ['email' => 'user@example.com', 'name' => 'John Doe']);
 */
class BrainEventClient
{
    public const CLIENT_VERSION = '1.0.0';

    private string $baseUrl;

    private string $apiKey;

    public function __construct(string $baseUrl, string $apiKey)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
    }

    /**
     * Get the client version.
     */
    public function getVersion(): string
    {
        return self::CLIENT_VERSION;
    }

    /**
     * Send an event to Brain Nucleus
     *
     * @param  string  $eventType  The type of event (e.g., 'user.signup', 'order.completed')
     * @param  array<string, mixed>  $payload  Event data (will be stored as JSON)
     * @param  \DateTimeInterface|null  $occurredAt  When the event occurred (defaults to now)
     * @return array<string, mixed>|null Response data with 'id' and 'status', or null on failure
     */
    public function send(string $eventType, array $payload, ?\DateTimeInterface $occurredAt = null): ?array
    {
        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::withHeaders([
                'X-Brain-Key' => $this->apiKey,
                'X-Brain-Client-Version' => self::CLIENT_VERSION,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/api/v1/events", [
                'event_type' => $eventType,
                'payload' => $payload,
                'occurred_at' => $occurredAt?->format('c'),
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('Brain event send failed', [
                'event_type' => $eventType,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Brain event send exception', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Send an event asynchronously (fire and forget)
     *
     * @param  array<string, mixed>  $payload
     */
    public function sendAsync(string $eventType, array $payload, ?\DateTimeInterface $occurredAt = null): void
    {
        dispatch(function () use ($eventType, $payload, $occurredAt) {
            $this->send($eventType, $payload, $occurredAt);
        });
    }

    /**
     * Check for client version updates.
     *
     * @return array<string, mixed>|null Response with 'latest_version', 'current_version', and 'update_required', or null on failure
     */
    public function checkVersion(): ?array
    {
        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = Http::get("{$this->baseUrl}/api/v1/client/version");

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('Brain version check failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Brain version check exception', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
