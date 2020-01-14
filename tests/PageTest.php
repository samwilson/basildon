<?php

namespace Test;

use App\Page;
use App\Site;
use PHPUnit\Framework\TestCase;

class PageTest extends TestCase
{

    public function testGetMetadata()
    {
        $site = new Site(__DIR__ . '/test_site');
        $file = new Page($site, '/simple');
        $metadata = ['title' => 'The title', 'tags' => 'one, two', 'template' => 'index'];
        static::assertEquals($metadata, $file->getMetadata());
        static::assertEquals('The body text.', $file->getBody());
    }

    public function testGetLink()
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
