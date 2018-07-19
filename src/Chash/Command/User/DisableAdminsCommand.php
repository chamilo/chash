<?php

namespace Chash\Command\User;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Class DisableAdminsCommand
 * Remove the "admin" role from *ALL* users on all portals of this instance
 * @package Chash\Command\User
 */
class DisableAdminsCommand extends CommonChamiloUserCommand
{
    /**
     *
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('user:disable_admins')
            ->setDescription('Changes all the admin users to teachers on the main portal (possible short term crack defense)');
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
        $connection = $this->getConnection($input);
        $helper = $this->getHelperSet()->get('question');
        $question = new ConfirmationQuestion(
            '<question>This action will make all admins normal teachers. Are you sure? (y/N)</question>',
            false
        );
        if (!$helper->ask($input, $output, $question)) {
            return;
        }

        $us = "DELETE FROM admin";
        $uq = mysql_query($us);
        if ($uq === false) {
            $output->writeln('Could not delete admins.');
        } else {
            $output->writeln('All admins disabled.');
        }
        return null;
    }
}
