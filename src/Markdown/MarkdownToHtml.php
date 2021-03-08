<?php

declare(strict_types=1);

namespace App\Markdown;

use cebe\markdown\Markdown;

class MarkdownToHtml extends Markdown
{
    use EmbedTrait;

    public function getFormat(): string
    {
        return 'html';
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
