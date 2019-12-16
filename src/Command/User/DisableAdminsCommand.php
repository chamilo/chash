<?php

namespace Chash\Command\User;

use Chash\Command\Common\DatabaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Class DisableAdminsCommand
 * Remove the "admin" role from *ALL* users on all portals of this instance.
 */
class DisableAdminsCommand extends DatabaseCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('user:disable_admins')
            ->setDescription('Changes all the admin users to teachers on the main portal (possible short term crack defense)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);
        $_configuration = $this->getHelper('configuration')->getConfiguration();
        $conn = $this->getConnection($input);
        $helper = $this->getHelperSet()->get('question');
        $question = new ConfirmationQuestion(
            '<question>This action will make all admins normal teachers. Are you sure? (y/N)</question>',
            false
        );
        if (!$helper->ask($input, $output, $question)) {
            return 0;
        }

        if ($conn instanceof \Doctrine\DBAL\Connection) {
            try {
                $us = 'DELETE FROM admin';
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
