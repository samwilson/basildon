<?php

declare(strict_types=1);

namespace App;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use stdClass;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class Site
{

    /** @var string */
    protected $dir;

    /** @var stdClass Runtime cache for the site config. */
    protected $config;

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
            ->name('*' . $this->getExt());
        $pages = [];
        foreach ($finder as $file) {
            $id = substr($file->getPathname(), strlen($this->getDir() . '/content'), -strlen($this->getExt()));
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
        if (is_object($this->config)) {
            return $this->config;
        }
        $configFile = $this->getDir() . '/config.yaml';
        if (!file_exists($configFile)) {
            $this->config = new stdClass;
            return $this->config;
        }
        $this->config = Yaml::parseFile($configFile, Yaml::PARSE_OBJECT_FOR_MAP);
        if ($this->config === null) {
            $this->config = new stdClass;
        }
        return $this->config;
    }

    /**
     * Get the filename extension used for content pages (inlcuding leading dot).
     * Will use what's defined in the site config key 'ext', or default to '.md'.
     *
     * @return string
     */
    public function getExt(): string
    {
        $ext = $this->getConfig()->ext;
        if (!$ext) {
            return 'md';
        }
        return '.' . ltrim($ext, '.');
    }

    public function getTitle(): string
    {
        $config = $this->getConfig();
        return $config->title;
    }
}
