<?php

declare(strict_types=1);

namespace App;

use Addwiki\Mediawiki\Api\Client\Action\ActionApi;
use Exception;
use GuzzleHttp\Client;
use stdClass;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

final class Site
{
    /** @var string */
    protected $dir;

    /** @var stdClass Runtime cache for the site config. */
    protected $config;

    /** @var Page[] */
    protected $pages;

    public function __construct(string $dir)
    {
        $this->dir = rtrim($dir, '/');
    }

    /**
     * Get the top-level site directory.
     *
     * @return string Never with a trailing slash.
     */
    public function getDir(): string
    {
        return $this->dir;
    }

    /**
     * @return Page[] Array of Pages indexed by page ID.
     */
    public function getPages(): array
    {
        if ($this->pages) {
            return $this->pages;
        }
        $finder = new Finder();
        $finder->files()
            ->in($this->getDir() . '/content')
            ->name('*' . $this->getExt());
        $pages = [];
        foreach ($finder as $file) {
            $idPath = substr($file->getPathname(), strlen($this->getDir() . '/content'), -strlen($this->getExt()));
            $id = str_replace(DIRECTORY_SEPARATOR, '/', $idPath);
            $page = new Page($this, $id);
            $pages[$page->getId()] = $page;
        }
        $this->pages = $pages;
        return $this->pages;
    }

    /**
     * @return Template[]
     */
    public function getTemplates(Database $db, string $prefix = ''): array
    {
        $templatesDir = $this->getDir() . '/templates';
        if (!is_dir($templatesDir)) {
            throw new Exception('Templates directory does not exist: ' . $templatesDir);
        }
        $finder = new Finder();
        $finder->files()
            ->in($templatesDir)
            ->path($prefix)
            ->name('*.twig');
        $templates = [];
        foreach ($finder as $file) {
            preg_match('/(.*)\.(.*)\.twig/', $file->getFilename(), $nameParts);
            $tplName = $file->getRelativePath() . '/' . $nameParts[1];
            $templates[] = new Template($db, $this, $tplName);
        }
        return $templates;
    }

    public function getConfig(): object
    {
        if (is_object($this->config)) {
            return $this->config;
        }

        // Load basildon.yaml
        $configFile = $this->getDir() . '/basildon.yaml';
        if (!file_exists($configFile)) {
            $this->config = new stdClass();
            return $this->config;
        }
        $config = file_get_contents($configFile);

        // Also load basildon.local.yaml
        $configLocal = $this->getDir() . '/basildon.local.yaml';
        if (file_exists($configLocal)) {
            $config .= file_get_contents($configLocal);
        }

        $this->config = Yaml::parse($config, Yaml::PARSE_OBJECT_FOR_MAP);
        if ($this->config === null) {
            $this->config = new stdClass();
        }
        return $this->config;
    }

    /**
     * Get the filename extension used for content pages (inlcuding leading dot).
     * Will use what's defined in the site config key 'ext', or default to '.md'.
     */
    public function getExt(): string
    {
        $ext = $this->getConfig()->ext ?? false;
        if (!$ext) {
            return '.md';
        }
        return '.' . ltrim($ext, '.');
    }

    public function getTitle(): string
    {
        $config = $this->getConfig();
        return $config->title ?? 'Untitled site';
    }

    /**
     * @return string The language code, or 'en' if none is specified in the site config.
     */
    public function getLang(): string
    {
        $config = $this->getConfig();
        return $config->lang ?? 'en';
    }

    public function getHttpClient(): Client
    {
        return new Client([
            'headers' => [
                'User-Agent' => 'Basildon https://basildon.samwilson.id.au - ' . $this->getTitle(),
            ],
        ]);
    }

    /**
     * @param string $apiUrl The URL to api.php for a MediaWiki wiki.
     */
    public function getMediawikiApi(string $apiUrl): ActionApi
    {
        return new ActionApi($apiUrl, null, $this->getHttpClient());
    }
}
