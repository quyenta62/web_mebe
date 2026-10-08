<?php

namespace App\Support;

/**
 * Parses the keyword box: "pass, bán, xe đẩy" -> ['pass', 'bán', 'xe đẩy'].
 * Comma separates keywords (combined with OR); a keyword containing spaces is one phrase.
 */
class KeywordParser
{
    public const MAX_KEYWORDS = 20;

    /** @return list<string> */
    public static function parse(?string $input): array
    {
        $keywords = [];
        foreach (explode(',', (string) $input) as $part) {
            $keyword = trim((string) preg_replace('/\s+/u', ' ', $part));
            if ($keyword === '') {
                continue;
            }
            // Case-insensitive dedupe, matching the case-insensitive search.
            $keywords[mb_strtolower($keyword)] ??= $keyword;
        }

        return array_values($keywords);
    }

    /** Escapes LIKE wildcards so "50%" or "a_b" match literally. */
    public static function escapeLike(string $keyword): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword);
    }
}
