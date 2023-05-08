<?php

declare(strict_types=1);

namespace Test;

use App\Database;
use App\Page;
use App\Site;
use App\Twig;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class TwigTest extends TestCase
{
    /** @var Database */
    private $db;

    public function setUp(): void
    {
        $this->db = new Database(__DIR__ . '/test_site/test.sqlite3');
    }

    /**
     * @covers \App\Twig::escapeCsv()
     * @covers \App\Twig::escapeTex()
     * @dataProvider provideEscape()
     */
    public function testEscape(string $strategy, ?string $in, string $out): void
    {
        $site = new Site(__DIR__ . '/test_site');
        $twig = new Twig($this->db, $site, new Page($site, '/simple'));
        $env = new Environment(new ArrayLoader());
        $escapeMethod = 'escape' . ucfirst($strategy);
        self::assertSame($out, $twig->$escapeMethod($env, $in));
    }

    /**
     * @return string[][]
     */
    public function provideEscape(): array
    {
        return [
            'csv' => [ 'csv', 'foo', 'foo' ],
            'csv quotes' => [ 'csv', 'the "foo" thing', '"the ""foo"" thing"' ],
            'csv commas' => [ 'csv', 'foo, bar', '"foo, bar"' ],
            'tex special chars' => [ 'tex', 'A$B"', 'A\textdollar B"' ],
            'tex allow null' => [ 'tex', null, '' ],
            'csv allow null' => [ 'csv', null, '' ],
        ];
    }

    /**
     * @covers Twig::functionQrCode()
     */
    public function testQrCode(): void
    {
        $site = new Site(__DIR__ . '/test_site');
        $twig = new Twig($this->db, $site, new Page($site, '/simple'));
        $out = $twig->functionQrCode('Lorem');
        self::assertSame('/assets/qrcodes/db6ff2ffe2df7b8cfc0d9542bdce27dc.svg', $out);
    }

    /**
     * @covers Twig::filterMarkdownToLatex()
     * @dataProvider provideImageUrlsToLatex()
     */
    public function testImageUrlsToLatex(string $markdown, string $latex): void
    {
        $site = new Site(__DIR__ . '/test_site');
        $twig = new Twig($this->db, $site, new Page($site, '/simple'));
        self::assertStringMatchesFormat($latex, $twig->filterMarkdownToLatex($markdown));
    }

    /**
     * @return string[][]
     */
    public function provideImageUrlsToLatex(): array
    {
        return [
            [
                'Foo ![bar](/subdir/Simple_shapes_example.png)',
                "Foo \includegraphics{%stests/test_site/content/subdir/Simple_shapes_example.png}\n",
            ],
            [
                'Foo ![bar](https://upload.wikimedia.org/wikipedia/commons/a/aa/Simple_shapes_example.png)',
                "Foo \includegraphics{%stests/test_site/cache/tex/_urls/67b86101a84e805c263e1315ee17e768.png}\n",
            ],
        ];
    }
}
