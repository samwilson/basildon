<?php

declare(strict_types=1);

namespace Test;

use App\Site;
use App\Template;
use PHPUnit\Framework\TestCase;

class TemplateTest extends TestCase
{

    public function testRenderSimple(): void
    {
        $site = new Site(__DIR__ . '/test_site');
        $tpl = new Template($site, 'test');
        $page = $site->getPages()['/simple'];
        $out = $tpl->renderSimple('tex', $page);
        static::assertSame('
\documentclass{article}

\begin{document}

The body text.



\end{document}

', $out);
    }

    public function testEmbeds(): void
    {
        $site = new Site(__DIR__ . '/test_site');
        $tpl = new Template($site, 'test');
        $page = $site->getPages()['/embeds'];
        $out = $tpl->renderSimple('tex', $page);
        static::assertStringMatchesFormat("
\documentclass{article}

\begin{document}

Test embeds. A file from Wikimedia Commons:



\begin{figure}
\begin{center}
\includegraphics[width=\linewidth]{%s/tests/test_site/cache/tex/_urls/c8746163efee06a4cd52b7d3f79327e8.png}
\caption{ A temporary file for testing of correct rendering of PNG files. }
\\end{center}
\\end{figure}



\\end{document}

", $out);
    }
}
