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
    private string $baseUrl;

    private string $apiKey;

    public function __construct(string $baseUrl, string $apiKey)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
    }

    /**
     * Send an event to Brain Nucleus
     *
     * @param  string  $eventType  The type of event (e.g., 'user.signup', 'order.completed')
     * @param  array<string, mixed>  $payload  Event data (will be stored as JSON)
     * @param  array<string, mixed>  $options  Optional fields: severity, fingerprint, message, context, occurred_at
     * @return array<string, mixed>|null Response data with 'id' and 'status', or null on failure
     */
    public function send(string $eventType, array $payload, array $options = []): ?array
    {
        try {
            $eventData = [
                'event_type' => $eventType,
                'payload' => $payload,
            ];

            // Add optional fields if provided
            if (isset($options['severity'])) {
                $eventData['severity'] = $options['severity'];
            }

            if (isset($options['fingerprint'])) {
                $eventData['fingerprint'] = $options['fingerprint'];
            }

            if (isset($options['message'])) {
                $eventData['message'] = $options['message'];
            }

            if (isset($options['context'])) {
                $eventData['context'] = $options['context'];
            }

            if (isset($options['occurred_at'])) {
                $eventData['occurred_at'] = $options['occurred_at'] instanceof \DateTimeInterface
                    ? $options['occurred_at']->format('c')
                    : $options['occurred_at'];
            }

            $response = Http::withHeaders([
                'X-Brain-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/api/v1/events", $eventData);

            if ($response instanceof \Illuminate\Http\Client\Response && $response->successful()) {
                return $response->json();
            }

            $status = $response instanceof \Illuminate\Http\Client\Response ? $response->status() : 'unknown';
            $body = $response instanceof \Illuminate\Http\Client\Response ? $response->body() : 'unknown';
            $headers = $response instanceof \Illuminate\Http\Client\Response ? $response->headers() : [];

            Log::warning('Brain event send failed', [
                'event_type' => $eventType,
                'status' => $status,
                'body' => $body,
                'base_url' => $this->baseUrl,
                'api_key_configured' => ! empty($this->apiKey),
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
     * @param  array<string, mixed>  $options
     */
    public function sendAsync(string $eventType, array $payload, array $options = []): void
    {
        dispatch(function () use ($eventType, $payload, $options) {
            $this->send($eventType, $payload, $options);
        });
    }
}
