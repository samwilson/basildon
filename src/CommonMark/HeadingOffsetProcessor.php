<?php

declare(strict_types=1);

namespace App\CommonMark;

use League\CommonMark\Environment\EnvironmentAwareInterface;
use League\CommonMark\Environment\EnvironmentInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Node\NodeIterator;
use League\Config\ConfigurationInterface;

/**
 * @psalm-suppress MissingConstructor
 */
final class HeadingOffsetProcessor implements EnvironmentAwareInterface
{
    private ?ConfigurationInterface $config;

    public function setEnvironment(EnvironmentInterface $environment): void
    {
        $this->config = $environment->getConfiguration();
    }

    public function __invoke(DocumentParsedEvent $event): void
    {
        if (!$this->config instanceof ConfigurationInterface) {
            return;
        }
        $headingOffset = (int) $this->config->get('basildon/heading_offset');
        foreach ($event->getDocument()->iterator(NodeIterator::FLAG_BLOCKS_ONLY) as $node) {
            if ($node instanceof Heading) {
                $node->setLevel($node->getLevel() + $headingOffset);
            }
        }
    }
}
