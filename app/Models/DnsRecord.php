<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $domain_id
 * @property string $host
 * @property string $type
 * @property string $value
 * @property int|null $ttl
 * @property int|null $priority
 * @property string|null $record_id
 * @property \Illuminate\Support\Carbon|null $synced_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Database\Factories\DnsRecordFactory factory()
 */
class DnsRecord extends Model
{
    /** @use HasFactory<\Database\Factories\DnsRecordFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'domain_id',
        'host',
        'type',
        'value',
        'ttl',
        'priority',
        'record_id',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'ttl' => 'integer',
            'priority' => 'integer',
            'synced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $record) {
            if (empty($record->id)) {
                $record->id = Str::uuid()->toString();
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
