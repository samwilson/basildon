<?php

declare(strict_types=1);

namespace Test;

use App\Page;
use App\Site;
use PHPUnit\Framework\TestCase;

class PageTest extends TestCase
{

    /**
     * @covers \App\Page::getMetadata()
     */
    public function testGetMetadata(): void
    {
        $site = new Site(__DIR__ . '/test_site');
        $file = new Page($site, '/simple');
        $metadata = ['title' => 'The title', 'tags' => 'one, two', 'template' => 'index'];
        static::assertEquals($metadata, $file->getMetadata());
        static::assertEquals('The body text.', $file->getBody());
    }

    /**
     * @covers \App\Page::getBody()
     */
    public function testGetBody(): void
    {
        $site = new Site(__DIR__ . '/test_site');
        $page = new Page($site, '/simple');
        static::assertSame('The body text.', $page->getBody());
    }

    /**
     * @covers \App\Page::getLink()
     */
    public function testGetLink(): void
    {
        $site = new Site(__DIR__ . '/test_site');

        $page1 = new Page($site, '/simple');
        static::assertEquals('subdir/foo', $page1->getLink('subdir/foo'));

        $page2 = new Page($site, '/subdir/foo');
        static::assertEquals('../simple', $page2->getLink('simple'));

        $page3 = new Page($site, '/subdir/subdir/deep');
        static::assertEquals('../../subdir/foo', $page3->getLink('subdir/foo'));
    }
}
