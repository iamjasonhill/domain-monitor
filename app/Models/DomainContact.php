<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $domain_id
 * @property string $contact_type
 * @property string|null $name
 * @property string|null $email_encrypted
 * @property string|null $phone_encrypted
 * @property string|null $organization
 * @property string|null $address_encrypted
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property string|null $country
 * @property \Illuminate\Support\Carbon $synced_at
 * @property array<string, mixed>|null $raw_data
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class DomainContact extends Model
{
    /** @use HasFactory<Factory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'domain_id',
        'contact_type',
        'name',
        'email_encrypted',
        'phone_encrypted',
        'organization',
        'address_encrypted',
        'city',
        'state',
        'postal_code',
        'country',
        'synced_at',
        'raw_data',
    ];

    protected function casts(): array
    {
        return [
            'synced_at' => 'datetime',
            'raw_data' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $contact) {
            if (empty($contact->id)) {
                $contact->id = Str::uuid()->toString();
            }
        });
    }

    /**
     * Get decrypted email
     */
    public function getEmail(): ?string
    {
        if (! $this->email_encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($this->email_encrypted);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set encrypted email
     */
    public function setEmail(?string $email): void
    {
        $this->email_encrypted = $email ? Crypt::encryptString($email) : null;
    }

    /**
     * Get decrypted phone
     */
    public function getPhone(): ?string
    {
        if (! $this->phone_encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($this->phone_encrypted);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set encrypted phone
     */
    public function setPhone(?string $phone): void
    {
        $this->phone_encrypted = $phone ? Crypt::encryptString($phone) : null;
    }

    /**
     * Get decrypted address
     */
    public function getAddress(): ?string
    {
        if (! $this->address_encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($this->address_encrypted);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set encrypted address
     */
    public function setAddress(?string $address): void
    {
        $this->address_encrypted = $address ? Crypt::encryptString($address) : null;
    }

    /**
     * @return BelongsTo<Domain, $this>
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
