<?php

declare(strict_types=1);

namespace App\Command;

use App\Database;
use App\Page;
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

class BuildCommand extends CommandBase
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
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $timeStart = microtime(true);
        static::$io = new SymfonyStyle($input, $output);
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
            $attrList[] = [ $column ];
        }
        static::$io->table(['Attributes'], $attrList);
        static::$io->write('Processing site . . . ');
        $db->processSite($site);
        static::$io->writeln('<info>OK</info>');

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
            static::writeln('<info>Page: ' . $page->getId() . '</info>');
            $site->getTemplate($page->getTemplateName())->render($page, $db);
        }

        // Build Lunr index as search.json.
        if ($input->getOption('lunr')) {
            static::writeln('Building search index...');
            $lunrBuilder = new BuildLunrIndex();
            $lunrBuilder->addPipeline('LunrPHP\LunrDefaultPipelines::trimmer');
            $lunrBuilder->addPipeline('LunrPHP\LunrDefaultPipelines::stop_word_filter');
            $lunrBuilder->addPipeline('LunrPHP\LunrDefaultPipelines::stemmer');
            $lunrBuilder->ref(Database::COL_NAME_ID);
            foreach ($db->getColumns($site) as $column) {
                $lunrBuilder->field($column);
            }
            $rows = $db->query('SELECT * from pages')->fetchAll(PDO::FETCH_ASSOC);
            $processBar = static::$io->createProgressBar(count($rows));
            foreach ($rows as $row) {
                $processBar->advance();
                $lunrBuilder->add($row);
            }
            $processBar->finish();
            static::writeln('');
            $lunrIndexFilename = $site->getDir() . '/output/lunr.json';
            file_put_contents($lunrIndexFilename, json_encode($lunrBuilder->output()));
            static::writeln('...index built: /lunr.json (' . round(filesize($lunrIndexFilename) / 1024) . 'KB)');
        }

        // Copy all other content files.
        $images = new Finder();
        $dir = $site->getDir();
        $images->files()
            ->in($dir . '/content')
            ->name('/.*\.(jpg|png|gif|svg|pdf)$/');
        foreach ($images as $image) {
            $assetRelativePath = substr($image->getRealPath(), strlen($dir . '/content'));
            static::writeln('Image: ' . $assetRelativePath);
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
                static::writeln('Asset: /assets/' . $asset->getFilename());
                copy($asset->getRealPath(), $assetsOutputDir . '/' . $asset->getFilename());
            }
        }

        $outDir = $site->getDir() . '/output/';
        $outputSizeCmd = new Process(['du', '-h', '-s', $outDir]);
        $outputSizeCmd->run();
        $outputSize = $outputSizeCmd->getOutput();
        static::$io->success([
            'Site output to ' . $outDir,
            'Memory usage: ' . (memory_get_peak_usage(true) / 1024 / 1024) . ' MiB',
            'Total time: ' . round(microtime(true) - $timeStart, 1) . ' seconds',
            'Output size: ' . substr($outputSize, 0, strpos($outputSize, "\t")),
        ]);
        return 0;
    }
}
