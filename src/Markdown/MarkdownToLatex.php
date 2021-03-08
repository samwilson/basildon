<?php

declare(strict_types=1);

namespace App\Markdown;

use App\Twig;
use cebe\markdown\latex\Markdown;
use Exception;

class MarkdownToLatex extends Markdown
{
    use EmbedTrait;

    public function getFormat(): string
    {
        return 'tex';
    }

    /**
     * @inheritdoc
     */
    protected function renderImage($block)
    {
        if (substr($block['url'], 0, 4) === 'http') {
            $twig = new Twig($this->site, $this->page);
            $link = $twig->functionTexUrl($block['url']);
        } else {
            $link = $this->site->getDir() . '/content' . $block['url'];
            if (!is_file($link)) {
                throw new Exception("Unable to find image: $link");
            }
        }
        return "\\includegraphics{{$link}}";
    }
}
