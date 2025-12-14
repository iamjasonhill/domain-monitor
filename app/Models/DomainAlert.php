<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $domain_id
 * @property string $alert_type
 * @property string $severity
 * @property \Illuminate\Support\Carbon $triggered_at
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property \Illuminate\Support\Carbon|null $notification_sent_at
 * @property \Illuminate\Support\Carbon|null $acknowledged_at
 * @property bool $auto_resolve
 * @property array<string, mixed>|null $payload
 *
 * @method static \Database\Factories\DomainAlertFactory factory()
 */
class DomainAlert extends Model
{
    /** @use HasFactory<\Database\Factories\DomainAlertFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'domain_id',
        'alert_type',
        'severity',
        'triggered_at',
        'resolved_at',
        'notification_sent_at',
        'acknowledged_at',
        'auto_resolve',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'triggered_at' => 'datetime',
            'resolved_at' => 'datetime',
            'notification_sent_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'auto_resolve' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $alert) {
            if (empty($alert->id)) {
                $alert->id = Str::uuid()->toString();
            }
        });
    }

    /**
     * @return BelongsTo<Domain, DomainAlert>
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
