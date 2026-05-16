<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $fleet_technical_seo_audit_run_id
 * @property string $check_id
 * @property string $target_type
 * @property string|null $target_url
 * @property string $result_status
 * @property string $evidence_confidence
 * @property array<string, mixed>|null $evidence
 * @property string|null $owner_system
 * @property string|null $monitoring_finding_id
 * @property string|null $owner_issue_url
 * @property FleetTechnicalSeoAuditRun $auditRun
 * @property MonitoringFinding|null $monitoringFinding
 */
class FleetTechnicalSeoAuditResult extends Model
{
    /** @use HasFactory<\Database\Factories\FleetTechnicalSeoAuditResultFactory> */
    use HasFactory;

    public const STATUS_PASS = 'pass';

    public const STATUS_FAIL = 'fail';

    public const STATUS_NOT_APPLICABLE = 'not_applicable';

    public const STATUS_MANUAL_REVIEW = 'manual_review';

    public const STATUS_UNKNOWN = 'unknown';

    public const CONFIDENCE_HIGH = 'high';

    public const CONFIDENCE_MEDIUM = 'medium';

    public const CONFIDENCE_LOW = 'low';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'fleet_technical_seo_audit_run_id',
        'check_id',
        'target_type',
        'target_url',
        'result_status',
        'evidence_confidence',
        'evidence',
        'owner_system',
        'monitoring_finding_id',
        'owner_issue_url',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $result): void {
            if (empty($result->id)) {
                $result->id = Str::uuid()->toString();
            }
        });
    }

    /**
     * @return BelongsTo<FleetTechnicalSeoAuditRun, $this>
     */
    public function auditRun(): BelongsTo
    {
        return $this->belongsTo(FleetTechnicalSeoAuditRun::class, 'fleet_technical_seo_audit_run_id');
    }

    /**
     * @return BelongsTo<MonitoringFinding, $this>
     */
    public function monitoringFinding(): BelongsTo
    {
        return $this->belongsTo(MonitoringFinding::class);
    }
}
