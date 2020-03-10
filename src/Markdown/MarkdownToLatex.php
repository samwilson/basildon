<?php

declare(strict_types=1);

namespace App\Markdown;

use cebe\markdown\latex\Markdown;

class MarkdownToLatex extends Markdown
{
    use EmbedTrait;

    /** @var string */
    protected $format = 'tex';
}
