<?php

namespace App\Models;

use Database\Factories\FacebookGroupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class FacebookGroup extends Model
{
    /** @use HasFactory<FacebookGroupFactory> */
    use HasFactory;

    // Deleting a group hides it but keeps its posts and crawl runs.
    use SoftDeletes;

    protected $fillable = [
        'facebook_group_id',
        'name',
        'url',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_crawled_at' => 'datetime',
        ];
    }

    /** @return HasMany<FacebookPost, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(FacebookPost::class);
    }

    /** @return HasMany<CrawlRun, $this> */
    public function crawlRuns(): HasMany
    {
        return $this->hasMany(CrawlRun::class);
    }

    /** @return HasOne<CrawlRun, $this> */
    public function latestCrawlRun(): HasOne
    {
        return $this->hasOne(CrawlRun::class)->latestOfMany();
    }

    /** @param Builder<FacebookGroup> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
