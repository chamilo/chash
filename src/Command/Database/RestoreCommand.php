<?php

namespace Chash\Command\Database;

use Chash\Command\Common\DatabaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Imports an SQL dump of the database (caller should use an output redirect of some kind
 * to store to a file).
 *
 * @param array $params params received
 */
class RestoreCommand extends DatabaseCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('db:restore')
            ->setDescription(
                'Restore an SQL dump into the active Chamilo database (which will also erase all previous data in that database)'
            )
            ->addArgument(
                'file',
                InputArgument::REQUIRED,
                'Specify the dump\'s full path, e.g. database:restore /tmp/dump.sql'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);
        $dumpPath = $input->getArgument('file');
        if (!is_dir($dumpPath) && file_exists($dumpPath)) {
            $_configuration = $this->getConfigurationArray();

            $output->writeln('<comment>Starting restoring database</comment>');
            $action = 'mysql -h '.$_configuration['db_host'].' -u '.$_configuration['db_user'].' -p'.$_configuration['db_password'].' '.$_configuration['main_database'].' < '.$dumpPath;
            system($action);
            $output->writeln('<info>Process ended succesfully</info>');
        } else {
            $output->writeln('<comment>File is not a valid SQL file: '.$dumpPath.' </comment>');
        }

        return 0;
    }
}
