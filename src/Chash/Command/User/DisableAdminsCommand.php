<?php

namespace Chash\Command\User;

use Chash\Command\Database\CommonDatabaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DisableAdminsCommand
 * Remove the "admin" role from *ALL* users on all portals of this instance
 * @package Chash\Command\User
 */
class DisableAdminsCommand extends CommonDatabaseCommand
{
    /**
     *
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('user:disable_admins')
            ->setDescription('Makes the given user admin on the main portal');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $_configuration = $this->getHelper('configuration')->getConfiguration();
        $conn = $this->getConnection($input);
        $dialog = $this->getHelperSet()->get('dialog');
        if (!$dialog->askConfirmation(
            $output,
            '<question>This action will make all admins normal teachers. Are you sure? (y/N)</question>',
            false
        )
        ) {
            return;
        }

        if ($conn instanceof \Doctrine\DBAL\Connection) {
            try {
                $us = "DELETE FROM admin";
                $stmt = $conn->prepare($us);
                $stmt->execute();
            } catch (\PDOException $e) {
                $output->writeln('Could not delete admins.');
                $output->write('SQL error!'.PHP_EOL);
                throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
            }
            $output->writeln('All admins have been disabled. Use user:make-admin to add one back.');
        }
        return null;
    }
}
