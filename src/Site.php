<?php

declare(strict_types=1);

namespace App;

use FilesystemIterator;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\FlysystemStorage;
use Kevinrob\GuzzleCache\Strategy\GreedyCacheStrategy;
use Kevinrob\GuzzleCache\Strategy\PrivateCacheStrategy;
use League\Flysystem\Adapter\Local;
use Mediawiki\Api\MediawikiApi;
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

    /** @var int Cache TTL in seconds. */
    protected $ttl;

    public function __construct(string $dir, int $cacheTtl = 60)
    {
        $this->dir = $dir;
        $this->ttl = $cacheTtl;
    }

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

    public function getConfig(): object
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
     * @return MediawikiApi
     */
    public function getMediawikiApi(string $apiUrl): MediawikiApi
    {
        $stack = HandlerStack::create();
        $stack->push(
            new CacheMiddleware(
                new GreedyCacheStrategy(
                    new FlysystemStorage(
                        new Local($this->getDir() . '/cache/mediawikiapi')
                    ),
                    $this->ttl
                )
            ),
            'mediawiki-api-cache'
        );
        return new MediawikiApi($apiUrl, new Client(['handler' => $stack]));
    }
}
