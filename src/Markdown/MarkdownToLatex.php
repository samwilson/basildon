<?php

declare(strict_types=1);

namespace App\Markdown;

use App\Twig;
use cebe\markdown\latex\Markdown;

class MarkdownToLatex extends Markdown
{
    use EmbedTrait;

    /** @var string */
    protected $format = 'tex';

    /**
     * @inheritdoc
     */
    protected function renderImage($block)
    {
        $link = $this->site->getDir() . '/assets/' . $block['url'];
        return "\\includegraphics{{$link}}";
    }
}
