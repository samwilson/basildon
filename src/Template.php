<?php

namespace App;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;

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

    protected function getTwig()
    {
        $loader = new FilesystemLoader();
        $loader->addPath($this->site->getDir() . '/templates');
        $twig = new Environment($loader, ['debug' => true, 'strict_variables' => true]);
        $twig->addExtension(new DebugExtension());
        return $twig;
    }

    public function getFormats()
    {
        $finder = new Finder();
        $finder->files()
            ->in($this->site->getDir() . '/templates')
            ->name($this->name . '*.twig');
        $formats = [];
        foreach ($finder as $file) {
            preg_match('/^.*\.(.*)\.twig$/', $file->getFilename(), $matches);
            $formats[] = $matches[1];
        }
        return $formats;
    }

    public function render(Page $page, Database $db)
    {
        foreach ($this->getFormats() as $format) {
            $renderedTemplate = $this->getTwig()->render($this->name . ".$format.twig", [
                'database' => $db,
                'site' => $page->getSite(),
                'page' => $page,
            ]);
            $outFile = $page->getSite()->getDir() . '/output' . $page->getId() . '.' . $format;
            if (!is_dir(dirname($outFile))) {
                mkdir(dirname($outFile), 0755, true);
            }
            file_put_contents($outFile, $renderedTemplate);

            // Post-process tex files to PDF.
            if ($format === 'tex') {
                $process = new Process([  ]);
            }
        }
    }
}
