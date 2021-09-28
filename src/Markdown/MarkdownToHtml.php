<?php

declare(strict_types=1);

namespace App\Markdown;

use cebe\markdown\Markdown;

class MarkdownToHtml extends Markdown
{
    use EmbedTrait;
    use FootnoteTrait;

    public function getFormat(): string
    {
        return 'html';
    }

    // phpcs:ignore
    public function parse($text)
    {
        return $this->addFootnotes(parent::parse($text));
    }

    /**
     * @inheritdoc
     */
    protected function renderImage($block)
    {
        if (substr($block['url'], 0, 4) !== 'http') {
            $block['url'] = $this->page->getLink($block['url']);
        }
        return parent::renderImage($block);
    }
}
