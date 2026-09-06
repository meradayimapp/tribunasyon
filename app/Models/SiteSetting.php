<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SiteSetting extends Model
{
    public const SINGLETON_ID = 1;

    public const MEDIA_COLUMNS = [
        'logo_path',
        'small_logo_path',
        'favicon_path',
        'light_logo_path',
        'dark_logo_path',
    ];

    protected $fillable = ['site_name', ...self::MEDIA_COLUMNS];

    public static function current(): self
    {
        if ($settings = static::query()->find(self::SINGLETON_ID)) {
            return $settings;
        }

        $settings = new self(['site_name' => config('app.name')]);
        $settings->setAttribute('id', self::SINGLETON_ID);

        return $settings;
    }

    public function displayName(): string
    {
        return $this->site_name ?: config('app.name');
    }

    public function mediaUrl(string $column): ?string
    {
        $path = in_array($column, self::MEDIA_COLUMNS, true) ? $this->{$column} : null;

        if (! $path || ! str_starts_with($path, 'branding/') || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    public function themeLogoUrl(string $theme, bool $compact = false): ?string
    {
        if ($compact && ($smallLogo = $this->mediaUrl('small_logo_path'))) {
            return $smallLogo;
        }

        $themeColumn = $theme === 'light' ? 'light_logo_path' : 'dark_logo_path';
        $alternateColumn = $theme === 'light' ? 'dark_logo_path' : 'light_logo_path';

        return $this->mediaUrl($themeColumn)
            ?? $this->mediaUrl('logo_path')
            ?? $this->mediaUrl($alternateColumn);
    }
}
