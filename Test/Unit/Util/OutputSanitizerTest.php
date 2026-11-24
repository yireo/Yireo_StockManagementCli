<?php
declare(strict_types=1);

namespace Yireo\StockManagementCli\Test\Unit\Util;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Yireo\StockManagementCli\Util\OutputSanitizer;

class OutputSanitizerTest extends TestCase
{
    /**
     * @dataProvider texts
     */
    #[DataProvider('texts')]
    public function testSanitizedTextIsRenderedLiterally(string $input, string $expected): void
    {
        $sanitized = (new OutputSanitizer())->sanitize($input);

        self::assertSame($expected, (new OutputFormatter(false))->format($sanitized));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function texts(): array
    {
        return [
            'plain' => ['Joust Duffle Bag', 'Joust Duffle Bag'],
            'multibyte' => ['Sac à dos ✓', 'Sac à dos ✓'],
            'formatter tags' => ['<error>Evil</error>', '<error>Evil</error>'],
            'trailing backslash' => ['C:\\', 'C:\\'],
            'ansi escape' => ["\e[31mRed\e[0m", '[31mRed[0m'],
            'osc sequence' => ["\e]8;;https://evil.test\x07link", ']8;;https://evil.test' . 'link'],
            'newlines and tabs' => ["a\nb\r\tc", 'abc'],
            'delete' => ["a\x7Fb", 'ab'],
            'c1 csi' => ["a\u{9B}31mb", 'a31mb'],
            'invalid utf8' => ["a\xC3b\e", 'ab'],
        ];
    }
}
