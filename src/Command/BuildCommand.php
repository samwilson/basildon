<?php

declare(strict_types=1);

namespace App\Command;

use App\Database;
use App\Page;
use App\Template;
use App\Util;
use LunrPHP\BuildLunrIndex;
use PDO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

final class BuildCommand extends CommandBase
{
    protected function configure(): void
    {
        parent::configure();
        $this->setName('build');
        $this->setDescription('Build a website.');
        $this->addOption('lunr', 'l', InputOption::VALUE_NONE, 'Build Lunr index?');
        $this->addOption('ttl', 't', InputOption::VALUE_REQUIRED, 'Set the cache TTL in seconds.', (string) (60 * 5));
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
        Util::cleanDir($site->getDir() . '/output', $site->getConfig()->output_exclude ?? []);

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
            $seconds = max(1, round(microtime(true) - $timeStartProcessing, 0));
            self::$io->writeln('<info>OK</info> (' . $seconds . ' ' . ($seconds > 1 ? 'seconds' : 'second') . ')');
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

        // Build Lunr index as search.json.
        if ($input->getOption('lunr')) {
            self::writeln('Building search index...');
            $lunrBuilder = new BuildLunrIndex();
            $lunrBuilder->addPipeline('LunrPHP\LunrDefaultPipelines::trimmer');
            $lunrBuilder->addPipeline('LunrPHP\LunrDefaultPipelines::stop_word_filter');
            $lunrBuilder->addPipeline('LunrPHP\LunrDefaultPipelines::stemmer');
            $lunrBuilder->ref(Database::COL_NAME_ID);
            foreach ($db->getColumns($site) as $column) {
                $lunrBuilder->field($column);
            }
            $rows = $db->query('SELECT * from pages')->fetchAll(PDO::FETCH_ASSOC);
            $processBar = self::$io->createProgressBar(count($rows));
            foreach ($rows as $row) {
                $processBar->advance();
                $lunrBuilder->add($row);
            }
            $processBar->finish();
            self::writeln('');
            $lunrIndexFilename = $site->getDir() . '/output/lunr.json';
            file_put_contents($lunrIndexFilename, json_encode($lunrBuilder->output()));
            self::writeln('...index built: /lunr.json (' . round(filesize($lunrIndexFilename) / 1024) . 'KB)');
        }

        // Copy all other content files.
        $images = new Finder();
        $dir = $site->getDir();
        $images->files()
            ->in($dir . '/content')
            ->name('/.*\.(jpg|png|gif|svg|pdf)$/');
        foreach ($images as $image) {
            $assetRelativePath = substr($image->getRealPath(), strlen($dir . '/content'));
            self::writeln('Image: ' . $assetRelativePath);
            Util::mkdir(dirname($dir . '/output' . $assetRelativePath));
            copy($image->getRealPath(), $dir . '/output' . $assetRelativePath);
        }

        // Copy all assets.
        // @TODO Add processing (LESS etc.).
        $assetsDir = $site->getDir() . '/assets';
        if (is_dir($assetsDir)) {
            $assets = new Finder();
            $assets->files()
                ->in($dir . '/assets')
                ->name('/.*\.(css|js|jpg|png|gif|svg|pdf)/');
            $assetsOutputDir = $dir . '/output/assets';
            Util::mkdir($assetsOutputDir);
            foreach ($assets as $asset) {
                self::writeln('Asset: /assets/' . $asset->getFilename());
                copy($asset->getRealPath(), $assetsOutputDir . '/' . $asset->getFilename());
            }
        }

        $outDir = $site->getDir() . '/output/';
        $outputSizeCmd = new Process(['du', '-h', '-s', $outDir]);
        $outputSizeCmd->run();
        $outputSize = $outputSizeCmd->getOutput();
        self::$io->success([
            'Site output to ' . $outDir,
            'Memory usage: ' . (memory_get_peak_usage(true) / 1024 / 1024) . ' MiB',
            'Total time: ' . round(microtime(true) - $timeStart, 1) . ' seconds',
            'Output size: ' . substr($outputSize, 0, strpos($outputSize, "\t")),
        ]);
        return 0;
    }
}
