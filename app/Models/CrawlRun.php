<?php

namespace App\Models;

use App\Enums\CrawlRunStatus;
use Database\Factories\CrawlRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrawlRun extends Model
{
    /** @use HasFactory<CrawlRunFactory> */
    use HasFactory;

    protected $fillable = [
        'facebook_group_id',
        'status',
        'max_posts',
        'posts_found',
        'posts_created',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => CrawlRunStatus::class,
            'max_posts' => 'integer',
            'posts_found' => 'integer',
            'posts_created' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * Includes soft-deleted groups so history still shows the group name.
     *
     * @return BelongsTo<FacebookGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(FacebookGroup::class, 'facebook_group_id')->withTrashed();
    }
}
