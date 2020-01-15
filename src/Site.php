<?php

declare(strict_types=1);

namespace App;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Finder\Finder;

class Site
{

    /** @var string */
    protected $dir;

    /** @var Page[] */
    protected $pages;

    public function __construct(string $dir)
    {
        $this->dir = $dir;
    }

    public function getDir(): string
    {
        return $this->dir;
    }

    /**
     * @return Page[]
     */
    public function getPages(): array
    {
        if ($this->pages) {
            return $this->pages;
        }
        $finder = new Finder;
        $finder->files()
            ->in($this->getDir() . '/content')
            ->name('*.txt');
        $pages = [];
        foreach ($finder as $file) {
            $id = substr($file->getPathname(), strlen($this->getDir() . '/content'), -4);
            $page = new Page($this, $id);
            $pages[$page->getId()] = $page;
        }
        $this->pages = $pages;
        return $this->pages;
    }

    public function getTemplate(string $name): Template
    {
        return new Template($this, $name);
    }

    protected function getConfig(): object
    {
        return json_decode(file_get_contents($this->dir . '/config.json'));
    }

    public function getTitle(): string
    {
        $config = $this->getConfig();
        return $config->title;
    }

    /**
     * Delete the output directory.
     *
     * @link https://stackoverflow.com/a/7288067/99667
     */
    public function cleanOutput(): void
    {
        $outDir = $this->getDir() . '/output';
        if (!is_dir($outDir)) {
            return;
        }
        $rdi = new RecursiveDirectoryIterator($outDir, FilesystemIterator::SKIP_DOTS);
        $rii = new RecursiveIteratorIterator($rdi, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($rii as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($outDir);
    }
}
