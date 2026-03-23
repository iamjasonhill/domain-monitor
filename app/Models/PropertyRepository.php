<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $web_property_id
 * @property string $repo_name
 * @property string $repo_provider
 * @property string|null $repo_url
 * @property string|null $local_path
 * @property string|null $default_branch
 * @property string|null $deployment_branch
 * @property string|null $framework
 * @property bool $is_primary
 * @property string|null $notes
 */
class PropertyRepository extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'web_property_id',
        'repo_name',
        'repo_provider',
        'repo_url',
        'local_path',
        'default_branch',
        'deployment_branch',
        'framework',
        'is_primary',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $repository) {
            if (empty($repository->id)) {
                $repository->id = Str::uuid()->toString();
            }
        });
    }

    /**
     * @return BelongsTo<WebProperty, $this>
     */
    public function webProperty(): BelongsTo
    {
        return $this->belongsTo(WebProperty::class);
    }
}
