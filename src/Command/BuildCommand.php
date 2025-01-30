<?php

declare(strict_types=1);

namespace App\Command;

use App\Database;
use App\Page;
use App\Template;
use App\Util;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

final class BuildCommand extends CommandBase
{
    /**
     * Get a human-readable representation of the time difference between the
     * start time and now.
     *
     * @param float $startTime The start time (acquired from e.g. `microtime(true)`).
     */
    public function getTimeElapsed(float $startTime): string
    {
        $total = round(microtime(true) - $startTime);
        $parts = [
            'hour' => 60 * 60,
            'minute' => 60,
            'second' => 1,
        ];
        $amounts = [];
        foreach ($parts as $partName => $partSize) {
            $amount = ($total - ( $total % $partSize ) ) / $partSize;
            $total = $total - ( $amount * $partSize);
            if ($amount) {
                $amounts[] = $amount . ' ' . $partName . ($amount > 1 ? 's' : '');
            }
        }
        if (!$amounts) {
            return 'no time';
        } elseif (count($amounts) === 1) {
            return $amounts[0];
        } else {
            return join(', ', $amounts);
        }
    }

    protected function configure(): void
    {
        parent::configure();
        $this->setName('build');
        $this->setDescription('Build a website.');
        $this->addOption(
            'page',
            'p',
            InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            'ID of a single page to render.',
            []
        );
        $this->addOption(
            'skip',
            's',
            InputOption::VALUE_NONE,
            'Skip processing of site pages, and use existing database.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $timeStart = microtime(true);
        self::$io = new SymfonyStyle($input, $output);
        $site = $this->getSite($input);
        if (!$site) {
            return Command::FAILURE;
        }

        // Clean output.
        Util::rmdir($site->getDir() . '/output');

        // First gather all data.
        $dbFile = $site->getDir() . '/cache/database/db.sqlite3';
        Util::mkdir(dirname($dbFile));
        $db = new Database($dbFile);
        $attrList = [];
        foreach ($db->getColumns($site) as $column) {
            $attrList[] = [$column];
        }
        self::$io->table(['Attributes'], $attrList);
        if ($input->getOption('skip')) {
            self::$io->warning("Skipping processing of pages. Using existing database at $dbFile");
        } else {
            self::$io->write('Processing site . . . ');
            $timeStartProcessing = microtime(true);
            $db->processSite($site);
            self::$io->writeln('<info>OK</info> (' . $this->getTimeElapsed($timeStartProcessing) . ')');
        }

        // Render all pages.
        $pages = [];
        if (count($input->getOption('page')) > 0) {
            foreach ($input->getOption('page') as $pageId) {
                $pages[] = new Page($site, $pageId);
            }
        } else {
            $pages = $site->getPages();
        }
        foreach ($pages as $page) {
            self::writeln('<info>Page: ' . $page->getId() . '</info>');
            $template = new Template($db, $site, $page->getTemplateName());
            $template->render($page);
        }

        // Copy all other content files.
        $files = new Finder();
        $dir = $site->getDir();
        $files->files()
            ->in($dir . '/content')
            ->notName('*' . $site->getExt());
        $this->copyFilesToOutput($dir . '/content', $dir . '/output', $files);

        // Copy all assets.
        $assetsDir = $dir . '/assets';
        if (is_dir($assetsDir)) {
            $assets = new Finder();
            $assets->files()
                ->in($assetsDir);
            $this->copyFilesToOutput($assetsDir, $dir . '/output', $assets);
        }

        // Report build details.
        $outDir = $site->getDir() . '/output/';
        $outputSizeCmd = new Process(['du', '-h', '-s', $outDir]);
        $outputSizeCmd->run();
        $outputSize = $outputSizeCmd->getOutput();
        self::$io->success([
            'Site output to ' . $outDir,
            'Memory usage: ' . (memory_get_peak_usage(true) / 1024 / 1024) . ' MiB',
            'Total time: ' . $this->getTimeElapsed($timeStart),
            'Output size: ' . substr($outputSize, 0, strpos($outputSize, "\t")),
        ]);
        return 0;
    }

    /**
     * @param string $inDir Full filesystem path of the source directory, with no trailing slash.
     * @param string $outDir Full filesystem path of the destination directory, with no trailing slash.
     * @param Finder $files The files to copy.
     */
    private function copyFilesToOutput(string $inDir, string $outDir, Finder $files): void
    {
        foreach ($files as $file) {
            $fileRelativePath = substr($file->getRealPath(), strlen($inDir));
            self::writeln('Copying file: ' . $fileRelativePath);
            Util::mkdir(dirname($outDir . $fileRelativePath));
            copy($file->getRealPath(), $outDir . $fileRelativePath);
        }
    }
}
