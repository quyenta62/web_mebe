<?php

namespace App\Models;

use App\Support\KeywordParser;
use Carbon\CarbonInterface;
use Database\Factories\FacebookPostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacebookPost extends Model
{
    /** @use HasFactory<FacebookPostFactory> */
    use HasFactory;

    protected $fillable = [
        'facebook_group_id',
        'facebook_post_id',
        'author_name',
        'content',
        'image_urls',
        'post_url',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
            'checked_at' => 'datetime',
            'image_urls' => 'array',
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

    /**
     * content LIKE '%k1%' OR content LIKE '%k2%' ..., wrapped in parentheses so the OR
     * never leaks into other filters. Case-insensitivity comes from the column collation.
     *
     * @param  Builder<FacebookPost>  $query
     * @param  list<string>  $keywords
     */
    public function scopeContainingAnyKeyword(Builder $query, array $keywords): void
    {
        if ($keywords === []) {
            return;
        }

        $query->where(function (Builder $query) use ($keywords) {
            foreach ($keywords as $keyword) {
                $query->orWhere('content', 'like', '%'.KeywordParser::escapeLike($keyword).'%');
            }
        });
    }

    /**
     * @param  Builder<FacebookPost>  $query
     */
    public function scopePostedBetween(Builder $query, ?CarbonInterface $from, ?CarbonInterface $toExclusive): void
    {
        if ($from !== null) {
            $query->where('posted_at', '>=', $from->copy()->utc());
        }
        if ($toExclusive !== null) {
            $query->where('posted_at', '<', $toExclusive->copy()->utc());
        }
    }
}
