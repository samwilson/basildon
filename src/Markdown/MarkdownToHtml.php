<?php

declare(strict_types=1);

namespace App\Markdown;

use cebe\markdown\Markdown;

class MarkdownToHtml extends Markdown
{
    use EmbedTrait;

    /** @var string */
    protected $format = 'html';
}
