<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DomainEligibilityCheck extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'domain_id',
        'source',
        'eligibility_type',
        'is_valid',
        'checked_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'is_valid' => 'boolean',
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
