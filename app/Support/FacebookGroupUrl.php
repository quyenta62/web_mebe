<?php

namespace App\Support;

/**
 * Parses and normalises Facebook group URLs, e.g.
 * https://m.facebook.com/groups/123456789/posts/1/ -> https://www.facebook.com/groups/123456789/
 */
class FacebookGroupUrl
{
    private const HOSTS = ['facebook.com', 'www.facebook.com', 'm.facebook.com', 'web.facebook.com'];

    /** Returns the group key (numeric ID or vanity name) or null when the URL is not a group URL. */
    public static function groupKey(string $url): ?string
    {
        $parts = parse_url(trim($url));
        if ($parts === false || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return null;
        }
        if (! in_array(strtolower($parts['host'] ?? ''), self::HOSTS, true) || isset($parts['user']) || isset($parts['port'])) {
            return null;
        }
        if (! preg_match('#^/groups/([A-Za-z0-9._-]{2,100})(/|$)#', $parts['path'] ?? '', $matches)) {
            return null;
        }

        return $matches[1];
    }

    public static function canonical(string $groupKey): string
    {
        return "https://www.facebook.com/groups/{$groupKey}/";
    }
}
