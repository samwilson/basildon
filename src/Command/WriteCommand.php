<?php

declare(strict_types=1);

namespace App\Command;

use App\Database;
use App\Page;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WriteCommand extends CommandBase
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
        foreach ($db->query('SELECT * FROM pages ORDER BY id') as $newMeta) {
            $page = new Page($site, $newMeta->id);
            $newBody = $newMeta->body;
            unset($newMeta->body, $newMeta->id);
            $page->write((array) $newMeta, $newBody);
        }
        return Command::SUCCESS;
    }
}
