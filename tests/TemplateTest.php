<?php

declare(strict_types=1);

namespace Test;

use App\Site;
use App\Template;
use PHPUnit\Framework\TestCase;

final class TemplateTest extends TestCase
{
    /**
     * @covers \App\Template::renderSimple
     */
    public function testRenderSimple(): void
    {
        $site = new Site(__DIR__ . '/test_site');
        $tpl = new Template($site, 'test');
        $page = $site->getPages()['/simple'];
        $out = $tpl->renderSimple('tex', $page);
        self::assertSame('
\documentclass{article}

\begin{document}

The body text.



\end{document}

', $out);
    }

    /**
     * @covers \App\Template::render
     */
    public function testShortcodes(): void
    {
        $site = new Site(__DIR__ . '/test_site');
        $tpl = new Template($site, 'test');
        $page = $site->getPages()['/shortcodes'];
        $out = $tpl->renderSimple('tex', $page);
        self::assertStringMatchesFormat("
\documentclass{article}

\begin{document}

Test shortcodes. A file from Wikimedia Commons:



\begin{figure}
\begin{center}
\includegraphics[width=\linewidth]{%stests/test_site/cache/tex/_urls/c8746163efee06a4cd52b7d3f79327e8.png}
\caption{ A temporary file for testing of correct rendering of PNG image files. }
\\end{center}
\\end{figure}




\\end{document}

", $out);
    }

    /**
     * @covers \App\Template::getFormats()
     */
    public function testGetFormats(): void
    {
        $site = new Site(__DIR__ . '/test_site');
        $tpl = new Template($site, 'test');
        $this->assertSame(['tex'], $tpl->getFormats());
    }
}
