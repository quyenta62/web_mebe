<?php

namespace App\Services\Crawler;

use App\Models\FacebookGroup;
use App\Models\FacebookPost;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Saves crawler rows for one group. Posts are unique per (group, Facebook post ID):
 * new posts are inserted, known posts are updated, and a value the crawler did not
 * get this time (null) never overwrites one it got before.
 */
class PostImporter
{
    private const UPDATABLE = ['author_name', 'content', 'image_urls', 'post_url', 'posted_at'];

    public const MAX_IMAGES = 10;

    /** @param  array<int, mixed>  $rows */
    public function import(FacebookGroup $group, array $rows): ImportResult
    {
        $posts = [];
        foreach ($rows as $row) {
            $post = $this->normalize($group, $row);
            if ($post === null) {
                Log::warning('Skipped invalid crawler row', ['group' => $group->facebook_group_id, 'row' => $row]);

                continue;
            }
            // Deduplicate within the batch too.
            $posts[$post['facebook_post_id']] = $post;
        }

        $skipped = count($rows) - count($posts);
        if ($posts === []) {
            return new ImportResult(count($rows), 0, 0, $skipped);
        }

        return DB::transaction(function () use ($group, $rows, $posts, $skipped) {
            $existing = FacebookPost::query()
                ->where('facebook_group_id', $group->id)
                ->whereIn('facebook_post_id', array_keys($posts))
                ->get()
                ->keyBy('facebook_post_id');

            $now = now();
            $new = [];
            $updated = 0;
            foreach ($posts as $postId => $data) {
                $post = $existing->get($postId);
                if ($post === null) {
                    // insertOrIgnore() skips model casts, so the array is encoded here.
                    $row = ['image_urls' => $data['image_urls'] === null ? null : json_encode($data['image_urls'])] + $data;
                    $new[] = $row + ['facebook_group_id' => $group->id, 'created_at' => $now, 'updated_at' => $now];

                    continue;
                }
                $post->fill(array_filter(array_intersect_key($data, array_flip(self::UPDATABLE)), fn ($value) => $value !== null));
                if ($post->isDirty()) {
                    $post->save();
                    $updated++;
                }
            }

            // The unique index (facebook_group_id, facebook_post_id) is the final guard against duplicates.
            $created = $new === [] ? 0 : FacebookPost::insertOrIgnore($new);

            return new ImportResult(count($rows), $created, $updated, $skipped);
        });
    }

    /** @return array<string, mixed>|null */
    private function normalize(FacebookGroup $group, mixed $row): ?array
    {
        if (! is_array($row)) {
            return null;
        }

        // A row of another group (e.g. a shared post) must never be attached to this one.
        if (isset($row['group_id']) && (string) $row['group_id'] !== $group->facebook_group_id) {
            return null;
        }

        // The crawler reports "<groupId>_<postId>"; the database stores the post part.
        $postId = (string) ($row['post_id'] ?? '');
        $prefix = $group->facebook_group_id.'_';
        if (str_starts_with($postId, $prefix)) {
            $postId = substr($postId, strlen($prefix));
        }
        if (! preg_match('/^\d{1,30}$/', $postId)) {
            return null;
        }

        return [
            'facebook_post_id' => $postId,
            'author_name' => $this->text($row['author_name'] ?? null, 255),
            'content' => $this->text($row['content'] ?? null, 60_000),
            'image_urls' => $this->imageUrls($row['image_urls'] ?? null),
            'post_url' => $this->facebookUrl($row['post_url'] ?? null),
            'posted_at' => $this->timestamp($row['posted_at'] ?? null),
        ];
    }

    private function text(mixed $value, int $maxLength): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, $maxLength);
    }

    /**
     * Only https URLs on Facebook's image CDN are kept: they are rendered as <img src>.
     * An empty list becomes null so a crawl without images never wipes known ones.
     *
     * @return list<string>|null
     */
    private function imageUrls(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $urls = [];
        foreach ($value as $url) {
            if (is_string($url) && strlen($url) <= 2048 && preg_match('#^https://[a-z0-9.-]+\.fbcdn\.net/#i', $url)) {
                $urls[$url] = $url;
            }
        }

        return $urls === [] ? null : array_slice(array_values($urls), 0, self::MAX_IMAGES);
    }

    /** Only https Facebook URLs are kept: they end up as links in the UI. */
    private function facebookUrl(mixed $value): ?string
    {
        if (! is_string($value) || strlen($value) > 500) {
            return null;
        }

        return preg_match('#^https://(www\.|m\.|web\.)?facebook\.com/#', $value) ? $value : null;
    }

    private function timestamp(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc()->toDateTimeString();
        } catch (Throwable) {
            return null;
        }
    }
}
