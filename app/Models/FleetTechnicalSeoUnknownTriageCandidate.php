<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $dedupe_key
 * @property string $web_property_id
 * @property string|null $domain_id
 * @property string $property_slug
 * @property string $audit_profile
 * @property string $coverage_unit
 * @property string $check_id
 * @property string $owner_route
 * @property string $latest_audit_run_id
 * @property string $latest_audit_result_id
 * @property int $retry_count
 * @property \Illuminate\Support\Carbon|null $first_seen_at
 * @property \Illuminate\Support\Carbon|null $last_seen_at
 * @property string $status
 * @property array<string, mixed> $candidate_payload
 */
class FleetTechnicalSeoUnknownTriageCandidate extends Model
{
    /** @use HasFactory<\Database\Factories\FleetTechnicalSeoUnknownTriageCandidateFactory> */
    use HasFactory;

    public const STATUS_OPEN = 'open';

    protected $fillable = [
        'dedupe_key',
        'web_property_id',
        'domain_id',
        'property_slug',
        'audit_profile',
        'coverage_unit',
        'check_id',
        'owner_route',
        'latest_audit_run_id',
        'latest_audit_result_id',
        'retry_count',
        'first_seen_at',
        'last_seen_at',
        'status',
        'candidate_payload',
    ];

    protected function casts(): array
    {
        return [
            'retry_count' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'candidate_payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<WebProperty, $this>
     */
    public function webProperty(): BelongsTo
    {
        return $this->belongsTo(WebProperty::class);
    }

    /**
     * @return BelongsTo<Domain, $this>
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * @return BelongsTo<FleetTechnicalSeoAuditRun, $this>
     */
    public function latestAuditRun(): BelongsTo
    {
        return $this->belongsTo(FleetTechnicalSeoAuditRun::class, 'latest_audit_run_id');
    }

    /**
     * @return BelongsTo<FleetTechnicalSeoAuditResult, $this>
     */
    public function latestAuditResult(): BelongsTo
    {
        return $this->belongsTo(FleetTechnicalSeoAuditResult::class, 'latest_audit_result_id');
    }
}
