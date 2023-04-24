<?php

declare(strict_types=1);

namespace App\Command;

use App\Database;
use App\Page;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

final class WriteCommand extends CommandBase
{
    protected function configure(): void
    {
        parent::configure();
        $this->setName('write');
        $this->setDescription('Write page contents from the database back into the Markdown files.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $site = $this->getSite($input);
        if (!$site) {
            return Command::FAILURE;
        }
        $dbFile = $site->getDir() . '/cache/database/db.sqlite3';
        $db = new Database($dbFile);
        foreach ($db->query('SELECT * FROM pages ORDER BY ' . Database::COL_NAME_ID) as $newMeta) {
            $page = new Page($site, $newMeta->{Database::COL_NAME_ID});
            $newBody = $newMeta->body;

            // Don't write id or body columns.
            unset($newMeta->{Database::COL_NAME_ID}, $newMeta->{Database::COL_NAME_BODY});

            // Parse each column's value.
            $parsedMetadata = [];
            foreach (array_filter((array) $newMeta) as $k => $v) {
                $parsedMetadata[$k] = Yaml::parse($v, Yaml::PARSE_DATETIME);
            }

            // Write the new data.
            $page->write(array_filter($parsedMetadata), $newBody);
        }
        return Command::SUCCESS;
    }
}
