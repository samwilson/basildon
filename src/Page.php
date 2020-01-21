<?php

declare(strict_types=1);

namespace App;

use Symfony\Component\Yaml\Yaml;

class Page
{

    /** @var Site */
    protected $site;

    /** @var string */
    protected $id;

    /** @var string */
    protected $contents;

    public function __construct(Site $site, string $id)
    {
        $this->site = $site;
        $this->id = '/' . ltrim($id, '/');
    }

    /**
     * Get the Site to which this Page belongs.
     *
     * @return Site
     */
    public function getSite(): Site
    {
        return $this->site;
    }

    /**
     * Page ID. Always starts with a slash.
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get a relative link from this page to another.
     *
     * @param string $targetId The ID of the target page.
     * @return string
     */
    public function getLink(string $targetId): string
    {
        // Make sure the target starts with a slash.
        if (substr($targetId, 0, 1) !== '/') {
            $targetId = "/$targetId";
        }

        // Split the paths into their constituent parts.
        $partsTarget = array_values(array_filter(explode('/', $targetId)));
        $partsThis = array_values(array_filter(explode('/', $this->getId())));

        $out = [];

        // Navigate back to the root.
        for ($i = 1; $i < count($partsThis); $i++) {
            $out[] = '..';
        }
        // Append target path.
        $out = array_merge($out, $partsTarget);

        return join('/', $out);
    }

    /**
     * Get the template's name, without format name or twig file extension.
     *
     * @return string
     */
    public function getTemplateName(): string
    {
        return $this->getMetadata()['template'];
    }

    protected function getContents(): string
    {
        if ($this->contents) {
            return $this->contents;
        }
        $filename = $this->site->getDir() . '/content' . $this->getId() . $this->site->getExt();
        $this->contents = file_get_contents($filename);
        return $this->contents;
    }

    /**
     * Get a file's metadata.
     *
     * @return string[]
     */
    public function getMetadata(): array
    {
        $contents = $this->getContents();
        preg_match("/---\n(.*)\n---/ms", $contents, $matches);
        $defaultMetadata = ['template' => 'index'];
        if (!isset($matches[1])) {
            return $defaultMetadata;
        }
        $parsedMetadata = Yaml::parse($matches[1], Yaml::PARSE_DATETIME);
        return array_merge($defaultMetadata, $parsedMetadata);
    }

    public function getBody(): string
    {
        $contents = $this->getContents();
        preg_match("/---\n.*\n---\n(.*)/ms", $contents, $matches);
        return isset($matches[1]) ? trim($matches[1]) : '';
    }
}
