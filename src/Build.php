<?php

declare(strict_types=1);

namespace App;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

class Build extends Command
{

    protected function configure(): void
    {
        parent::configure();
        $this->setName('build');
        $this->addArgument('dir', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dir = realpath($input->getArgument('dir'));
        if (!$dir || !is_dir($dir)) {
            $output->writeln('bad directory');
            return 1;
        }
        $site = new Site($dir);

        // Clean output.
        $site->cleanOutput();

        // First gather all data.
        $db = new Database;
        $db->processSite($site);

        // Render all pages.
        foreach ($site->getPages() as $page) {
            $output->writeln('Page: ' . $page->getId());
            $site->getTemplate($page->getTemplateName())->render($page, $db);
        }

        // Copy all assets.
        $assets = new Finder;
        $assets->files()
            ->in($dir . '/assets')
            ->name('/.*\.(css|js|jpg|png|gif)/');
        $assetsOutputDir = $dir . '/output/assets';
        if (!is_dir($assetsOutputDir)) {
            mkdir($assetsOutputDir);
        }
        foreach ($assets as $asset) {
            $output->writeln('Asset: ' . $asset->getFilename());
            copy($asset->getRealPath(), $assetsOutputDir . '/' . $asset->getFilename());
        }

        return 0;
    }
}
