<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $domain_id
 * @property bool $is_compliant
 * @property string|null $compliance_reason
 * @property string $source
 * @property \Illuminate\Support\Carbon $checked_at
 * @property array<string, mixed>|null $payload
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class DomainComplianceCheck extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'domain_id',
        'is_compliant',
        'compliance_reason',
        'source',
        'checked_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'is_compliant' => 'boolean',
            'checked_at' => 'datetime',
            'payload' => 'array',
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
     * @return BelongsTo<Domain, $this>
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
