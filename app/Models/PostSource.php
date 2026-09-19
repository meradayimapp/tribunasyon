<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostSource extends Model
{
    protected $fillable = ['post_id', 'url', 'label', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    protected function domain(): Attribute
    {
        return Attribute::get(function (): string {
            $host = strtolower((string) parse_url($this->url, PHP_URL_HOST));

            return preg_replace('/^www\./i', '', $host) ?: $this->url;
        });
    }

    protected function displayLabel(): Attribute
    {
        return Attribute::get(fn (): string => filled($this->label) ? $this->label : $this->domain);
    }
}
