<?php

declare(strict_types=1);

namespace App\Markdown;

use App\Page;
use App\Site;

trait EmbedTrait
{

    /** @var Site */
    protected $site;

    /** @var Page */
    protected $page;

    public function setSite(Site $site): void
    {
        $this->site = $site;
    }

    public function setPage(Page $page): void
    {
        $this->page = $page;
    }

    /**
     * Get the name of the format that this Embed produces.
     */
    abstract protected function getFormat(): string;

    /**
     * @param string[] $lines
     */
    protected function identifyEmbed(string $line, array $lines, int $current): bool
    {
        $config = $this->site->getConfig();
        if (!isset($config->embeds)) {
            return false;
        }
        foreach ($config->embeds as $embedPattern) {
            if (preg_match($embedPattern, $line)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string[] $lines
     * @return mixed[]|null
     */
    protected function consumeEmbed(array $lines, int $current): ?array
    {
        foreach ($this->site->getConfig()->embeds as $embedName => $embedPattern) {
            if (preg_match($embedPattern, $lines[$current], $matches)) {
                return [
                    [
                        'embed',
                        'name' => $embedName,
                        'url' => $lines[$current],
                        'matches' => $matches,
                    ],
                    $current,
                ];
            }
        }
    }

    /**
     * @param mixed[] $embed
     */
    protected function renderEmbed(array $embed): string
    {
        $template = $this->site->getTemplate('embeds/' . $embed['name']);
        return $template->renderSimple($this->getFormat(), $this->page, [
            'site' => $this->site,
            'page' => $this->page,
            'embed' => $embed,
        ]);
    }
}
