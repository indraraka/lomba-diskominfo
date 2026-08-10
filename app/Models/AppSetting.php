<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $fillable = [
        'brand_name', 'logo_url', 'primary_color', 'primary_dark_color', 'accent_color',
    ];

    public static function defaults(): array
    {
        return [
            'brand_name' => 'Lomba Diskominfo',
            'logo_url' => '/images/logo-indramayu.png',
            'primary_color' => '#0067A5',
            'primary_dark_color' => '#00476F',
            'accent_color' => '#E5B52A',
        ];
    }

    public static function branding(): array
    {
        $settings = static::query()->first();

        return $settings
            ? $settings->only(array_keys(static::defaults()))
            : static::defaults();
    }
}
