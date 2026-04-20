<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $key
 * @property string $name
 * @property string $version
 * @property string $contract_type
 * @property string $status
 * @property string|null $scope
 * @property string|null $source_repo
 * @property string|null $source_path
 * @property array<string, mixed>|null $contract
 * @property string|null $notes
 */
class AnalyticsEventContract extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'name',
        'version',
        'contract_type',
        'status',
        'scope',
        'source_repo',
        'source_path',
        'contract',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'contract' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $contract) {
            if (empty($contract->id)) {
                $contract->id = Str::uuid()->toString();
            }
        });
    }

    /**
     * @return HasMany<WebPropertyEventContract, $this>
     */
    public function propertyAssignments(): HasMany
    {
        return $this->hasMany(WebPropertyEventContract::class)->orderByDesc('is_primary');
    }
}
