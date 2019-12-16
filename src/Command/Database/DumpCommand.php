<?php

namespace Chash\Command\Database;

use Chash\Command\Common\DatabaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DumpCommand.
 *
 * Returns a dump of the database (caller should use an output redirect of some
 * kind to store to a file.
 *
 * @package Chash\Command\Database
 */
class DumpCommand extends DatabaseCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('db:dump')
            ->setDescription('Outputs a dump of the database');
    }

    /**
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $_configuration = $this->getConfigurationArray();
        $dump = 'mysqldump -h '.$_configuration['db_host'].' -u '.$_configuration['db_user'].' -p'.$_configuration['db_password'].' '.$_configuration['main_database'];
        system($dump);

        return null;
    }
}
