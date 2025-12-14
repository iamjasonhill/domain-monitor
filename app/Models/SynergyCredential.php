<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $reseller_id
 * @property string $api_key_encrypted
 * @property string|null $api_url
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $last_sync_at
 * @property string|null $notes
 */
class SynergyCredential extends Model
{
    /** @use HasFactory<\Database\Factories\SynergyCredentialFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'reseller_id',
        'api_key_encrypted',
        'api_url',
        'is_active',
        'last_sync_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_sync_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $credential) {
            if (empty($credential->id)) {
                $credential->id = Str::uuid()->toString();
            }
        });
    }

    /**
     * Get decrypted API key
     */
    public function getApiKey(): string
    {
        return Crypt::decryptString($this->api_key_encrypted);
    }

    /**
     * Set encrypted API key
     */
    public function setApiKey(string $apiKey): void
    {
        $this->api_key_encrypted = Crypt::encryptString($apiKey);
    }
}
