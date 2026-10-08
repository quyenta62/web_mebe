<?php

namespace App\Exceptions;

use RuntimeException;

/** Thrown by the crawl job on a temporary failure so the queue retries it. */
class CrawlAttemptFailed extends RuntimeException {}
