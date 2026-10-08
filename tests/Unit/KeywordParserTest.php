<?php

namespace Tests\Unit;

use App\Support\KeywordParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class KeywordParserTest extends TestCase
{
    /**
     * @return array<string, array{0: ?string, 1: list<string>}>
     */
    public static function inputs(): array
    {
        return [
            'two keywords' => ['pass, bán', ['pass', 'bán']],
            'phrase keeps its spaces' => ['pass, bán, xe đẩy', ['pass', 'bán', 'xe đẩy']],
            'extra whitespace collapsed' => ["  pass ,   xe    đẩy\t, ", ['pass', 'xe đẩy']],
            'empty parts dropped' => [', , ,', []],
            'empty string' => ['', []],
            'null' => [null, []],
            'case-insensitive dedupe keeps first spelling' => ['Pass, PASS, pass, Bán, BÁN', ['Pass', 'Bán']],
            'accents are distinct keywords' => ['bán, ban, bạn', ['bán', 'ban', 'bạn']],
            'special characters kept' => ["50%, a_b, it's", ['50%', 'a_b', "it's"]],
        ];
    }

    #[DataProvider('inputs')]
    public function test_parse(?string $input, array $expected): void
    {
        $this->assertSame($expected, KeywordParser::parse($input));
    }

    public function test_escape_like_wildcards(): void
    {
        $this->assertSame('50\\%', KeywordParser::escapeLike('50%'));
        $this->assertSame('a\\_b', KeywordParser::escapeLike('a_b'));
        $this->assertSame('c:\\\\x', KeywordParser::escapeLike('c:\\x'));
    }
}
