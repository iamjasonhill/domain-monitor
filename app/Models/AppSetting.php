<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AppSetting extends Model
{
    /** @use HasFactory<\Database\Factories\AppSettingFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $setting) {
            if (empty($setting->id)) {
                $setting->id = Str::uuid()->toString();
            }
        });
    }
}
