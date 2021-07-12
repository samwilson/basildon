<?php

declare(strict_types=1);

namespace Test;

use App\Page;
use App\Site;
use App\Twig;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class TwigTest extends TestCase
{

    /**
     * @covers \App\Twig::escapeCsv()
     * @covers \App\Twig::escapeTex()
     * @dataProvider provideEscapeCsv
     */
    public function testEscape(string $strategy, string $in, string $out): void
    {
        $site = new Site(__DIR__ . '/test_site');
        $twig = new Twig($site, new Page($site, '/simple'));
        $env = new Environment(new ArrayLoader());
        $escapeMethod = 'escape' . ucfirst($strategy);
        static::assertSame($out, $twig->$escapeMethod($env, $in));
    }

    /**
     * @return string[][]
     */
    public function provideEscapeCsv(): array
    {
        return [
            'csv' => [ 'csv', 'foo', 'foo' ],
            'csv quotes' => [ 'csv', 'the "foo" thing', '"the ""foo"" thing"' ],
            'csv commas' => [ 'csv', 'foo, bar', '"foo, bar"' ],
            'tex special chars' => [ 'tex', 'A$B"', 'A\textdollar B"' ],
        ];
    }

    /**
     * @covers Twig::functionQrCode()
     */
    public function testQrCode(): void
    {
        $site = new Site(__DIR__ . '/test_site');
        $twig = new Twig($site, new Page($site, '/simple'));
        $out = $twig->functionQrCode('Lorem');
        static::assertSame('/assets/qrcodes/db6ff2ffe2df7b8cfc0d9542bdce27dc.svg', $out);
    }
}
