<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $issue_id
 * @property string|null $property_slug
 * @property string|null $domain
 * @property string|null $issue_class
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $hidden_until
 * @property string|null $verification_source
 * @property array<int, string>|null $verification_notes
 * @property \Illuminate\Support\Carbon $verified_at
 */
class DetectedIssueVerification extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'issue_id',
        'property_slug',
        'domain',
        'issue_class',
        'status',
        'hidden_until',
        'verification_source',
        'verification_notes',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'hidden_until' => 'datetime',
            'verification_notes' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $verification): void {
            if (empty($verification->id)) {
                $verification->id = Str::uuid()->toString();
            }
        });
    }
}
