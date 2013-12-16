<?php

namespace Chash\Command\User;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Command functions meant to deal with what the user of this script is calling
 * it for.
 */
/**
 * Class CreateCommand
 * Creates a new user
 * @package Chash\Command\User
 */
class CreateCommand extends CommonChamiloUserCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('user:create')
            ->setDescription('Creates a new user')
            ->addArgument(
                'username',
                InputArgument::REQUIRED,
                'Allows you to specify the username'
            )
            ->addArgument(
                'password',
                InputArgument::REQUIRED,
                'The password to give this user'
            )
            ->addArgument(
                'role',
                InputArgument::OPTIONAL,
                'The role for the new user - must be one of student or teacher (optional, defaults to student)'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $_configuration = $this->getHelper('configuration')->getConfiguration();
        $dbh = $this->getHelper('configuration')->getConnection();
        $username = $input->getArgument('username');
        $password = $input->getArgument('password');
        $enc = $_configuration['password_encryption'];
        switch ($enc) {
            case 'sha1':
                $password = sha1($password);
                break;
            case 'md5':
                $password = md5($password);
                break;
            default:
                $password = mysql_real_escape_string($password);
                break;
        }
        $role = $input->getArgument('role');
        $dbRole = ($role == 'teacher' ? 1 : 5);
        //Include Chamilo's UserManager class
        $us = "INSERT INTO user (username, password, status) VALUES ('".mysql_real_escape_string($username)."', '".mysql_real_escape_string($password)."','".mysql_real_escape_string($dbRole)."')";
        $uq = mysql_query($us);
        $un = mysql_num_rows($uq);
        if ($un >= 1) {
            $output->writeln('User '.$username.' has been created.');
        } else {
            $output->writeln('Could not create user '.$username);
        }
        return null;
    }
}
