<?php

declare(strict_types=1);

namespace App;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\FlysystemStorage;
use Kevinrob\GuzzleCache\Strategy\GreedyCacheStrategy;
use League\Flysystem\Adapter\Local;
use Mediawiki\Api\MediawikiApi;
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

    /** @var int Cache TTL in seconds. */
    protected $ttl;

    public function __construct(string $dir, ?int $cacheTtl = null)
    {
        $this->dir = rtrim($dir, '/');
        $this->ttl = $cacheTtl ?? 60;
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

    public function getTtl(): int
    {
        return $this->ttl;
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

        // Load config.yaml
        $configFile = $this->getDir() . '/config.yaml';
        if (!file_exists($configFile)) {
            $this->config = new stdClass();
            return $this->config;
        }
        $config = file_get_contents($configFile);

        // Also load config.local.yaml
        $configLocal = $this->getDir() . '/config.local.yaml';
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

    /**
     * @param string $apiUrl The URL to api.php for a MediaWiki wiki.
     */
    public function getMediawikiApi(string $apiUrl): MediawikiApi
    {
        $stack = HandlerStack::create();
        $cacheDir = $this->getDir() . '/cache/mediawikiapi/' . preg_replace('/[^a-z0-9]/', '', $apiUrl);
        $stack->push(
            new CacheMiddleware(
                new GreedyCacheStrategy(
                    new FlysystemStorage(new Local($cacheDir)),
                    $this->ttl
                )
            ),
            'mediawiki-api-cache'
        );
        return new MediawikiApi($apiUrl, new Client(['handler' => $stack]));
    }
}
