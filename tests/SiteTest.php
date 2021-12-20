<?php

declare(strict_types=1);

namespace Test;

use App\Page;
use App\Site;
use PHPUnit\Framework\TestCase;

class SiteTest extends TestCase
{
    /**
     * @covers \App\Site::getPages()
     */
    public function testBasics(): void
    {
        $site = new Site(__DIR__ . '/test_site');
        static::assertCount(4, $site->getPages());
    }
}
