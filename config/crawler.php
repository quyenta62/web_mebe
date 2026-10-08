<?php

return [

    /*
    | Phase 1 crawler (Node.js + Playwright). Must be built (`npm run build`) and
    | hold the Facebook session at storage/facebook-state.json (`npm run login`).
    | In Docker it is mounted at /var/www/crawler.
    */
    'path' => env('CRAWLER_PATH', base_path('../crawler_mebe')),

    'node_binary' => env('CRAWLER_NODE_BINARY', 'node'),

    // Limits passed to the crawler on every run (crawler bounds: posts 1-200, scrolls 0-100).
    'max_posts' => (int) env('CRAWLER_MAX_POSTS', 50),
    'max_scrolls' => (int) env('CRAWLER_MAX_SCROLLS', 15),

    // Overall crawl timeout (crawler bounds: 10-1800 s). The process is killed 60 s later.
    'timeout_seconds' => (int) env('CRAWLER_TIMEOUT_SECONDS', 300),

    /*
    | Queue (Phase 2.5). Crawl jobs run on their own queue; at most
    | `max_concurrent` browser sessions run at once, whatever the number of workers.
    | A pending/running run older than `stale_after_minutes` is considered dead.
    */
    'queue' => env('CRAWLER_QUEUE', 'crawler'),
    'max_concurrent' => (int) env('CRAWLER_MAX_CONCURRENT', 1),
    'stale_after_minutes' => (int) env('CRAWLER_STALE_AFTER_MINUTES', 180),

];
