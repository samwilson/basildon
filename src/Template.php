<?php

declare(strict_types=1);

namespace App;

use App\Command\CommandBase;
use Exception;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Twig\Cache\FilesystemCache;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\FilesystemLoader;
use Twig\Runtime\EscaperRuntime;

/**
 * A Template belongs to a Site and can be used to render Pages to various formats.
 */
final class Template
{
    /** @var Site */
    protected $site;

    /** @var string The filesystem name of this template. */
    protected $name;

    /** @var Database */
    private $db;

    public function __construct(Database $db, Site $site, string $name)
    {
        $this->db = $db;
        $this->site = $site;
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string[]
     */
    public function getFormats(): array
    {
        $templatesDir = $this->site->getDir() . '/templates';
        if (!is_dir($templatesDir)) {
            throw new Exception('Templates directory does not exist: ' . $templatesDir);
        }
        $finder = new Finder();
        $finder->files()
            ->in($templatesDir)
            ->path(dirname($this->name))
            ->name(basename($this->name) . '.*.twig');
        $formats = [];
        foreach ($finder as $file) {
            preg_match('/^.*\.(.*)\.twig$/', $file->getFilename(), $matches);
            $formats[] = $matches[1];
        }
        if (empty($formats)) {
            throw new Exception(
                'No formats found for template: ' . $this->name . "\n"
                . '(e.g. for HTML, create ' . $templatesDir . '/' . $this->name . '.html.twig)'
            );
        }
        return $formats;
    }

    /**
     * Render a simple template that doesn't need the database.
     *
     * @param string $format The format to render.
     * @param mixed[]|null $params The parameters to pass to the template.
     */
    public function renderSimple(string $format, Page $page, ?array $params = null): string
    {
        $allParams = array_merge([
            'database' => $this->db,
            'site' => $page->getSite(),
            'page' => $page,
        ], $params ?? []);
        return $this->getTwig($page)->render($this->name . ".$format.twig", $allParams);
    }

    public function render(Page $page): void
    {
        foreach ($this->getFormats() as $format) {
            $renderedTemplate = $this->getTwig($page)->render($this->name . ".$format.twig", [
                'database' => $this->db,
                'site' => $page->getSite(),
                'page' => $page,
            ]);
            $outFileBase = $page->getSite()->getDir() . '/output' . $page->getId();

            if ($format === 'tex') {
                // Save tex source file.
                $texOutFileBase = $page->getSite()->getDir() . '/cache/tex' . $page->getId();
                $texOutFile = $texOutFileBase . '.tex';
                Util::mkdir(dirname($texOutFile));
                file_put_contents($texOutFile, $renderedTemplate);
                // Generate PDF.
                $pdfDir = dirname($texOutFile);
                CommandBase::writeln('Compiling PDF for: ' . $page->getId());
                $args = ['latexmk', '-lualatex', "-auxdir=$pdfDir", "-outdir=$pdfDir", $texOutFile];
                $process = new Process($args, $pdfDir);
                $process->setTimeout(10 * 60);
                $process->mustRun();
                // Copy PDF to output directory.
                Util::mkdir(dirname($outFileBase));
                if (!file_exists($texOutFileBase . '.pdf')) {
                    throw new Exception('Unable to generate PDF from ' . $texOutFile);
                }
                copy($texOutFileBase . '.pdf', $outFileBase . '.pdf');
            } else {
                // Save rendered template to output directory.
                $outFile = $outFileBase . '.' . $format;
                Util::mkdir(dirname($outFile));
                file_put_contents($outFile, $renderedTemplate);
            }
        }
    }

    /**
     * Get the Twig Environment.
     */
    protected function getTwig(Page $page): Environment
    {
        $loader = new FilesystemLoader();
        $loader->addPath($this->site->getDir() . '/templates');
        $twig = new Environment($loader, [
            'debug' => true,
            'strict_variables' => true,
            'cache' => new FilesystemCache($this->site->getDir() . '/cache/twig/'),
            'use_yield' => true,
        ]);
        $twig->addExtension(new IntlExtension());
        $twig->addExtension(new DebugExtension());
        $twigExtension = new Twig($this->db, $this->site, $page);
        $twig->addExtension($twigExtension);
        $escaperRuntime = $twig->getRuntime(EscaperRuntime::class);
        $escaperRuntime->setEscaper('tex', [$twigExtension, 'escapeTex']);
        $escaperRuntime->setEscaper('csv', [$twigExtension, 'escapeCsv']);
        return $twig;
    }
}
