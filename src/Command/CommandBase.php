<?php

declare(strict_types=1);

namespace App\Command;

use App\Site;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CommandBase extends Command
{
    /** @var SymfonyStyle */
    public static $io;

    public static function writeln(string $line): void
    {
        if (self::$io instanceof SymfonyStyle) {
            self::$io->writeln($line);
        }
    }

    protected function configure(): void
    {
        parent::configure();
        $this->addArgument(
            'dir',
            InputArgument::REQUIRED,
            "The site's root directory, containing <comment>content/</comment>, <comment>assets/</comment>, etc."
        );
    }

    protected function getSite(InputInterface $input): ?Site
    {
        $dir = realpath($input->getArgument('dir'));
        if (!$dir || !is_dir($dir)) {
            self::$io->error('Directory not found: ' . $input->getArgument('dir'));
            return null;
        }
        $ttl = $input->hasOption('ttl') ? (int) $input->getOption('ttl') : null;
        return new Site($dir, $ttl);
    }
}
