<?php

namespace App\Models;

use App\Support\PublicUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** Bosh sahifadagi reklama karuseli bannerlari */
class Banner extends Model
{
    protected $fillable = ['title', 'url', 'image_path', 'is_active', 'starts_at', 'ends_at', 'sort', 'clicks'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public static function disk(): string
    {
        return (string) config('savdodaftar.banners.disk', 'public');
    }

    public function getImageUrlAttribute(): string
    {
        return (string) PublicUrl::for(self::disk(), $this->image_path);
    }

    /** Faol, boshlangan va muddati tugamagan bannerlar */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function isVisible(): bool
    {
        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->lte(now()))
            && ($this->ends_at === null || $this->ends_at->gte(now()));
    }
}
