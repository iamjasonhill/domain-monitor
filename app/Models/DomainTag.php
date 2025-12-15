<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $name
 * @property int $priority
 * @property string|null $color
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class DomainTag extends Model
{
    use HasFactory, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'priority',
        'color',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $tag) {
            if (empty($tag->id)) {
                $tag->id = Str::uuid()->toString();
            }
        });
    }

    /**
     * @return BelongsToMany<Domain, DomainTag>
     */
    public function domains(): BelongsToMany
    {
        return $this->belongsToMany(Domain::class, 'domain_tag', 'tag_id', 'domain_id');
    }

    /**
     * Scope a query to order tags by priority (descending).
     */
    public function scopeOrderedByPriority($query): void
    {
        $query->orderBy('priority', 'desc');
    }
}
