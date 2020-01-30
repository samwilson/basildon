<?php

declare(strict_types=1);

namespace App;

use LunrPHP\BuildLunrIndex;
use PDO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

class Build extends Command
{

    protected function configure(): void
    {
        parent::configure();
        $this->setName('build');
        $this->addArgument('dir', InputArgument::REQUIRED);
        $this->addOption('lunr', 'l', InputOption::VALUE_NONE, 'Build Lunr index?');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $timeStart = microtime(true);
        $io = new SymfonyStyle($input, $output);
        $dir = realpath($input->getArgument('dir'));
        if (!$dir || !is_dir($dir)) {
            $output->writeln('bad directory');
            return 1;
        }
        $site = new Site($dir);

        // Clean output.
        Util::rmdir($site->getDir() . '/output');
        Util::rmdir($site->getDir() . '/tex');

        // First gather all data.
        $db = new Database;
        $attrList = [];
        foreach ($db->getColumns($site) as $column) {
            $attrList[] = [ $column ];
        }
        $io->table(['Attributes'], $attrList);
        $db->processSite($site);

        // Render all pages.
        foreach ($site->getPages() as $page) {
            $io->writeln('Page: ' . $page->getId());
            $site->getTemplate($page->getTemplateName())->render($page, $db);
        }

        // Build Lunr index as search.json.
        if ($input->getOption('lunr')) {
            $io->writeln('Building search index...');
            $lunrBuilder = new BuildLunrIndex;
            $lunrBuilder->addPipeline('LunrPHP\LunrDefaultPipelines::trimmer');
            $lunrBuilder->addPipeline('LunrPHP\LunrDefaultPipelines::stop_word_filter');
            $lunrBuilder->addPipeline('LunrPHP\LunrDefaultPipelines::stemmer');
            $lunrBuilder->ref(Database::COL_NAME_ID);
            foreach ($db->getColumns($site) as $column) {
                $lunrBuilder->field($column);
            }
            $rows = $db->query('SELECT * from pages')->fetchAll(PDO::FETCH_ASSOC);
            $processBar = $io->createProgressBar(count($rows));
            foreach ($rows as $row) {
                $processBar->advance();
                $lunrBuilder->add($row);
            }
            $processBar->finish();
            $io->writeln('');
            $lunrIndexFilename = $site->getDir() . '/output/lunr.json';
            file_put_contents($lunrIndexFilename, json_encode($lunrBuilder->output()));
            $io->writeln('...index built: /lunr.json (' . round(filesize($lunrIndexFilename) / 1024) . 'KB)');
        }

        // Copy all assets.
        $assetsDir = $site->getDir() . '/assets';
        if (is_dir($assetsDir)) {
            $assets = new Finder;
            $assets->files()
                ->in($dir . '/assets')
                ->name('/.*\.(css|js|jpg|png|gif)/');
            $assetsOutputDir = $dir . '/output/assets';
            Util::mkdir($assetsOutputDir);
            foreach ($assets as $asset) {
                $output->writeln('Asset: /assets/' . $asset->getFilename());
                copy($asset->getRealPath(), $assetsOutputDir . '/' . $asset->getFilename());
            }
        }

        $io->success([
            'Site output to ' . $site->getDir() . '/output/',
            'Total time: ' . round(microtime(true) - $timeStart, 1) . ' seconds.',
        ]);
        return 0;
    }
}
