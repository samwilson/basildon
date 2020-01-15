<?php

declare(strict_types=1);

namespace Test;

use App\Page;
use App\Site;
use PHPUnit\Framework\TestCase;

class SiteTest extends TestCase
{

    public function testBasics(): void
    {
        $site = new Site(__DIR__ . '/test_site');
        static::assertCount(3, $site->getPages());
    }
}
