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
     * @dataProvider provideEscapeCsv
     */
    public function testEscapeCsv(string $in, string $out): void
    {
        $site = new Site(__DIR__ . '/test_site');
        $twig = new Twig($site, new Page($site, '/simple'));
        $env = new Environment(new ArrayLoader());
        static::assertSame($out, $twig->escapeCsv($env, $in));
    }

    /**
     * @return string[][]
     */
    public function provideEscapeCsv(): array
    {
        return [
            'simple' => [ 'foo', 'foo' ],
            'quotes' => [ 'the "foo" thing', '"the ""foo"" thing"' ],
            'commas' => [ 'foo, bar', '"foo, bar"' ],
        ];
    }
}
