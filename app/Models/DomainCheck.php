<?php

namespace App\Models;

use App\Services\BrainEventClient;
use App\Services\DomainCheckAlertingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $domain_id
 * @property string $check_type
 * @property string $status
 * @property int|null $response_code
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property int|null $duration_ms
 * @property string|null $error_message
 * @property array<string, mixed>|null $payload
 * @property array<string, mixed>|null $metadata
 * @property int $retry_count
 *
 * @method static \Database\Factories\DomainCheckFactory factory()
 */
class DomainCheck extends Model
{
    /** @use HasFactory<\Database\Factories\DomainCheckFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'domain_id',
        'check_type',
        'status',
        'response_code',
        'started_at',
        'finished_at',
        'duration_ms',
        'error_message',
        'payload',
        'metadata',
        'retry_count',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'metadata' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_ms' => 'integer',
            'retry_count' => 'integer',
            'response_code' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $check) {
            if (empty($check->id)) {
                $check->id = Str::uuid()->toString();
            }
        });

        static::created(function (self $check) {
            app(DomainCheckAlertingService::class)->handle($check);
        });
    }

    /**
     * Emit domain.check.* event to Brain
     */
    public function emitBrainEvent(): void
    {
        // Only emit if Brain is configured
        $brain = app(BrainEventClient::class);
        $baseUrl = config('services.brain.base_url');
        $apiKey = config('services.brain.api_key');

        if (empty($baseUrl) || empty($apiKey)) {
            return; // Skip if not configured
        }

        // Load domain relationship if not already loaded
        if (! $this->relationLoaded('domain')) {
            $this->load('domain');
        }

        $domain = $this->domain;
        if (! $domain) {
            return; // Skip if domain not found
        }

        // Determine severity based on status
        $severity = match ($this->status) {
            'ok' => 'info',
            'warn' => 'warning',
            'fail' => 'error',
            default => 'info',
        };

        // Create event type: domain.check.{check_type}
        $eventType = "domain.check.{$this->check_type}";

        // Create stable fingerprint for grouping
        $fingerprint = "domain.check:{$this->check_type}:{$domain->domain}";

        // Create message
        $message = sprintf(
            'Domain check %s for %s: %s',
            $this->check_type,
            $domain->domain,
            $this->status
        );

        // Build context
        $context = [
            'domain' => $domain->domain,
            'domain_id' => $domain->id,
            'check_type' => $this->check_type,
            'status' => $this->status,
        ];

        if ($this->response_code) {
            $context['response_code'] = $this->response_code;
        }

        if ($this->duration_ms) {
            $context['duration_ms'] = $this->duration_ms;
        }

        // Build payload
        $payload = [
            'domain' => $domain->domain,
            'domain_id' => $domain->id,
            'check_id' => $this->id,
            'check_type' => $this->check_type,
            'status' => $this->status,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'duration_ms' => $this->duration_ms,
        ];

        if ($this->response_code) {
            $payload['response_code'] = $this->response_code;
        }

        if ($this->error_message) {
            $payload['error_message'] = $this->error_message;
        }

        if ($this->payload) {
            $payload['check_payload'] = $this->payload;
        }

        if ($this->metadata) {
            $payload['metadata'] = $this->metadata;
        }

        // Send event asynchronously (non-blocking)
        $brain->sendAsync($eventType, $payload, [
            'severity' => $severity,
            'fingerprint' => $fingerprint,
            'message' => $message,
            'context' => $context,
            'occurred_at' => $this->finished_at ?? $this->created_at,
        ]);
    }

    /**
     * @return BelongsTo<Domain, DomainCheck>
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
