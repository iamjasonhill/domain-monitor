<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DomainCheckAlertState extends Model
{
    /** @use HasFactory<\Database\Factories\DomainCheckAlertStateFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'domain_id',
        'check_type',
        'consecutive_failure_count',
        'alert_active',
        'alerted_at',
        'recovered_at',
    ];

    protected function casts(): array
    {
        return [
            'consecutive_failure_count' => 'integer',
            'alert_active' => 'boolean',
            'alerted_at' => 'datetime',
            'recovered_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $state) {
            if (empty($state->id)) {
                $state->id = Str::uuid()->toString();
            }
        });
    }

    /**
     * @return BelongsTo<Domain, DomainCheckAlertState>
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
