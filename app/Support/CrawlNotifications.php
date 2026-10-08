<?php

namespace App\Support;

use App\Enums\CrawlRunStatus;
use App\Models\CrawlRun;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * Remembers (in the session) the crawls started from the UI in this browser, so the next
 * page shows "in progress" while they run and a one-time notice once they finish.
 */
class CrawlNotifications
{
    private const SESSION_KEY = 'watched_crawl_runs';

    private const MAX_WATCHED = 200;

    public static function watch(Session $session, CrawlRun $run): void
    {
        $ids = $session->get(self::SESSION_KEY, []);
        $ids[] = $run->id;
        $session->put(self::SESSION_KEY, array_slice(array_values(array_unique($ids)), -self::MAX_WATCHED));
    }

    /**
     * Finished runs are returned once and forgotten; unfinished ones stay watched.
     *
     * @return array{finished: Collection<int, CrawlRun>, running: Collection<int, CrawlRun>}
     */
    public static function pull(Session $session): array
    {
        $ids = $session->get(self::SESSION_KEY, []);
        if ($ids === []) {
            return ['finished' => collect(), 'running' => collect()];
        }

        $runs = CrawlRun::with('group')->whereIn('id', $ids)->orderBy('id')->get();
        [$finished, $running] = $runs->partition(
            fn (CrawlRun $run) => in_array($run->status, [CrawlRunStatus::Success, CrawlRunStatus::Failed], true)
        );
        $session->put(self::SESSION_KEY, $running->pluck('id')->values()->all());

        return ['finished' => $finished->values(), 'running' => $running->values()];
    }
}
