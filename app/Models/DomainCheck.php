<?php

namespace App\Models;

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
    }

    /**
     * @return BelongsTo<Domain, DomainCheck>
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
