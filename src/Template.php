<?php

declare(strict_types=1);

namespace App;

use Exception;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * A Template belongs to a Site and can be used to render Pages to various formats.
 */
class Template
{

    /** @var Site */
    protected $site;

    /** @var string The filesystem name of this template. */
    protected $name;

    public function __construct(Site $site, string $name)
    {
        $this->site = $site;
        $this->name = $name;
    }

    /**
     * Get the Twig Environment.
     *
     * @return Environment
     */
    protected function getTwig(): Environment
    {
        $loader = new FilesystemLoader;
        $loader->addPath($this->site->getDir() . '/templates');
        $twig = new Environment($loader, ['debug' => true, 'strict_variables' => true]);
        $twig->addExtension(new DebugExtension);
        $twig->addFunction(new TwigFunction('instanceof', static function ($a, $b) {
            return $a instanceof $b;
        }));
        return $twig;
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
        $finder = new Finder;
        $finder->files()
            ->in($templatesDir)
            ->name($this->name . '*.twig');
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

    public function render(Page $page, Database $db): void
    {
        foreach ($this->getFormats() as $format) {
            $renderedTemplate = $this->getTwig()->render($this->name . ".$format.twig", [
                'database' => $db,
                'site' => $page->getSite(),
                'page' => $page,
            ]);
            $outFileBase = $page->getSite()->getDir() . '/output' . $page->getId();

            if ($format === 'tex') {
                // Save tex source file.
                $texOutFileBase = $page->getSite()->getDir() . '/tex' . $page->getId();
                $texOutFile = $texOutFileBase . '.tex';
                $this->mkdir(dirname($texOutFile));
                file_put_contents($texOutFile, $renderedTemplate);
                // Generate PDF.
                $process = new Process(['pdflatex', '-output-directory', dirname($texOutFile), $texOutFile]);
                $process->mustRun();
                // Copy PDF to output directory.
                copy($texOutFileBase . '.pdf', $outFileBase . '.pdf');
            } else {
                // Save rendered template to output directory.
                $outFile = $outFileBase . '.' . $format;
                $this->mkdir(dirname($outFile));
                file_put_contents($outFile, $renderedTemplate);
            }
        }
    }

    protected function mkdir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
