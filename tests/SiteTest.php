<?php

declare(strict_types=1);

namespace Test;

use App\Site;
use PHPUnit\Framework\TestCase;

final class SiteTest extends TestCase
{
    /**
     * @covers \App\Site::getPages()
     */
    public function testBasics(): void
    {
        $site = new Site(__DIR__ . '/test_site');
        self::assertCount(4, $site->getPages());
    }
}
