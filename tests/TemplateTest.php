<?php

declare(strict_types=1);

namespace Test;

use App\Database;
use App\Site;
use App\Template;
use PHPUnit\Framework\TestCase;

final class TemplateTest extends TestCase
{
    /** @var Database */
    private $db;

    /** @var Site */
    private $site;

    public function setUp(): void
    {
        $this->db = new Database();
        $this->site = new Site(__DIR__ . '/test_site');
        $this->db->processSite($this->site);
    }

    /**
     * @covers \App\Template::renderSimple
     */
    public function testRenderSimple(): void
    {
        $tpl = new Template($this->db, $this->site, 'test');
        $page = $this->site->getPages()['/simple'];
        $out = $tpl->renderSimple('tex', $page);
        self::assertSame('
\documentclass{article}
\usepackage{graphicx}

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
        $tpl = new Template($this->db, $this->site, 'test');
        $page = $this->site->getPages()['/shortcodes'];
        $tpl->render($page);
        $texFile = __DIR__ . '/test_site/cache/tex/shortcodes.tex';
        self::assertFileExists($texFile);
        $out = file_get_contents($texFile);
        self::assertSame("
\documentclass{article}
\usepackage{graphicx}

\begin{document}

\\emph{Test \\textsc{shortcodes}.} A file from Wikimedia Commons:



\begin{figure}
\begin{center}
\includegraphics[width=\linewidth]{_urls/ed72ca4dc7f3111aa40562c71d4f5477.png}
\caption{ A temporary file for testing of correct rendering of PNG image files. }
\\end{center}
\\end{figure}

There are 4 pages.




\\end{document}

", $out);
    }

    /**
     * @covers \App\Template::getFormats()
     */
    public function testGetFormats(): void
    {
        $tpl = new Template($this->db, $this->site, 'test');
        $this->assertSame(['tex'], $tpl->getFormats());
    }
}
