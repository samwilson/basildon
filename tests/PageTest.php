<?php

declare(strict_types=1);

namespace Test;

use App\Page;
use App\Site;
use DateTime;
use PHPUnit\Framework\TestCase;

class PageTest extends TestCase
{
    /**
     * @covers \App\Page::getMetadata()
     */
    public function testGetMetadata(): void
    {
        $site = new Site(__DIR__ . '/test_site');
        $page1 = new Page($site, '/simple');
        $metadata = ['template' => 'index', 'title' => 'The title', 'tags' => ['one', 'two']];
        static::assertSame($metadata, $page1->getMetadata());
        static::assertSame('The body text.', $page1->getBody());

        // No metadata.
        $page2 = new Page($site, '/subdir/subdir/deep');
        static::assertSame(['template' => 'index'], $page2->getMetadata());

        // Invalid YAML metadata.
        $page3 = new Page($site, '/subdir/foo');
        static::assertSame(['template' => 'index'], $page3->getMetadata());
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

    /**
     * @covers \App\Page::write()
     */
    public function testWrite(): void
    {
        $site = new Site(__DIR__ . '/test_site');
        $page = new Page($site, '/simple');

        // Check zero-modification.
        $metaOriginal = $page->getMetadata();
        $page->write($metaOriginal, $page->getBody());
        static::assertSame('The body text.', $page->getBody());
        static::assertSame(
            "---\ntemplate: index\ntitle: 'The title'\ntags:\n    - one\n    - two\n---\nThe body text.\n",
            $page->getContents()
        );

        // Change a metadata field.
        $metaNew = $metaOriginal;
        $metaNew['tags'][] = 'new tag';
        $page->write($metaNew, $page->getBody());
        static::assertSame(
            "---\ntemplate: index\ntitle: 'The title'\n"
            . "tags:\n    - one\n    - two\n    - 'new tag'\n---\nThe body text.\n",
            $page->getContents()
        );
        static::assertSame($metaNew, $page->getMetadata());

        // Replace all metadata.
        $page->write(['foo' => 'bar'], $page->getBody());
        static::assertSame("---\nfoo: bar\n---\nThe body text.\n", $page->getContents());

        // Reset to original.
        $page->write($metaOriginal, $page->getBody());

        // Create some new pages.
        $newPage = new Page($site, '/subdir/new-page');
        $newPage->write(['date' => new DateTime('2020-01-01 02:03:04Z')], 'Test');
        static::assertSame("---\ndate: 2020-01-01T02:03:04+00:00\n---\nTest\n", $newPage->getContents());
        $newPage2 = new Page($site, '/subdir/new-page-2');
        $newPage2->write(['int_number' => 123], '');
        static::assertSame("---\nint_number: 123\n---\n", $newPage2->getContents());

        // Edit a page with no body.
        $newPage2->write(['foo' => 'bar'], '');
        static::assertSame("---\nfoo: bar\n---\n", $newPage2->getContents());

        // Clean up.
        unlink($newPage->getFilename());
        unlink($newPage2->getFilename());
    }

    /**
     * @covers Page::write()
     * @dataProvider provideWriting
     *
     * @param mixed[] $metadata
     */
    public function testWriting(string $pageText, array $metadata, string $body): void
    {
        $site = new Site(__DIR__ . '/test_site');
        file_put_contents($site->getDir() . '/content/test_writing.txt', $pageText);
        $page = new Page($site, '/test_writing');
        static::assertSame($metadata, $page->getMetadata());
        static::assertSame($body, $page->getBody());
        $page->write($metadata, $body);
        static::assertSame($metadata, $page->getMetadata());
        static::assertSame($body, $page->getBody());
        unlink($page->getFilename());
    }

    /**
     * @return mixed[][]
     */
    public function provideWriting(): array
    {
        return [
            'only Yaml no newline' => [
                "---\ntemplate: lorem\n---",
                ['template' => 'lorem'],
                '',
            ],
            'only Yaml with multiple newlines' => [
                "---\ntemplate: lorem\n---\n\n",
                ['template' => 'lorem'],
                '',
            ],
            'only frontmatter, four hyphens' => [
                "----\ntemplate: lorem\n----",
                ['template' => 'lorem'],
                '',
            ],
            'only body no newline' => [
                'lorem ipsum',
                ['template' => 'index'],
                'lorem ipsum',
            ],
            'only body multiple newlines' => [
                "\n\nlorem ipsum\n\n\n",
                ['template' => 'index'],
                'lorem ipsum',
            ],
            'both Yaml and body' => [
                "---\ntemplate: lorem\n---\nIpsum.\n",
                ['template' => 'lorem'],
                'Ipsum.',
            ],
        ];
    }
}
