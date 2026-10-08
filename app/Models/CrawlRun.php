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

    /** Seconds between start and finish, or null while unfinished. */
    public function durationSeconds(): ?int
    {
        if ($this->started_at === null || $this->finished_at === null) {
            return null;
        }

        return (int) $this->started_at->diffInSeconds($this->finished_at, true);
    }

    /** The error code before the first colon, e.g. "LOGIN_REQUIRED". */
    public function errorCode(): ?string
    {
        if ($this->error_message === null || ! str_contains($this->error_message, ':')) {
            return null;
        }

        return strstr($this->error_message, ':', true);
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
