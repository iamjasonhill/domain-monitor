<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $issue_id
 * @property string $lane
 * @property string $finding_type
 * @property string $issue_type
 * @property string $scope_type
 * @property string|null $domain_id
 * @property string|null $web_property_id
 * @property string $status
 * @property string $title
 * @property string|null $summary
 * @property \Illuminate\Support\Carbon|null $first_detected_at
 * @property \Illuminate\Support\Carbon|null $last_detected_at
 * @property \Illuminate\Support\Carbon|null $recovered_at
 * @property array<string, mixed>|null $evidence
 * @property Domain|null $domain
 * @property WebProperty|null $webProperty
 */
class MonitoringFinding extends Model
{
    /** @use HasFactory<\Database\Factories\MonitoringFindingFactory> */
    use HasFactory;

    public const STATUS_OPEN = 'open';

    public const STATUS_RECOVERED = 'recovered';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'issue_id',
        'lane',
        'finding_type',
        'issue_type',
        'scope_type',
        'domain_id',
        'web_property_id',
        'status',
        'title',
        'summary',
        'first_detected_at',
        'last_detected_at',
        'recovered_at',
        'evidence',
    ];

    protected function casts(): array
    {
        return [
            'first_detected_at' => 'datetime',
            'last_detected_at' => 'datetime',
            'recovered_at' => 'datetime',
            'evidence' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $finding): void {
            if (empty($finding->id)) {
                $finding->id = Str::uuid()->toString();
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

    /**
     * @return BelongsTo<WebProperty, $this>
     */
    public function webProperty(): BelongsTo
    {
        return $this->belongsTo(WebProperty::class);
    }
}
