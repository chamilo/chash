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
 * Class AddUserCommand
 * Changes a user password to the one given
 * @package Chash\Command\User
 */
class AddUserCommand extends CommonChamiloUserCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('user:add_user')
            ->setDescription('Add a new user')
            ->addArgument(
                'firstname',
                InputArgument::REQUIRED,
                'Allows you to specify the firstname'
            )
            ->addArgument(
                'lastname',
                InputArgument::REQUIRED,
                'Allows you to specify the lastname'
            )
            ->addArgument(
                'username',
                InputArgument::REQUIRED,
                'Allows you to specify the username'
            )
            ->addArgument(
                'email',
                InputArgument::REQUIRED,
                'Allows you to specify the email'
            )
            ->addArgument(
                'password',
                InputArgument::REQUIRED,
                'The new password to give this user'
            )
            ->addArgument(
                'role',
                InputArgument::OPTIONAL,
                'The user role: anonymous, student (default), teacher, admin'
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
        $connection = $this->getConnection($input);
        $firstname = $input->getArgument('firstname');
        $lastname = $input->getArgument('lastname');
        $username = $input->getArgument('username');
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');
        $role = $input->getArgument('role');
        $us = "SELECT * FROM user WHERE username = '".mysql_escape_string($username)."'";
        $uq = mysql_query($us);
        $un = mysql_num_rows($uq);
        if ($un === 0) {
            $enc = $_configuration['password_encryption'];
            switch ($enc) {
                case 'bcrypt':
                    $password = password_hash($password, PASSWORD_BCRYPT, 4);
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
            $numRole = 5;
            $isAdmin = 0;
            switch ($role) {
                case 'anonymous':
                    $numRole = 6;
                    break;
                case 'teacher':
                    $numRole = 1;
                    break;
                case 'admin':
                    $numRole = 1;
                    $isAdmin = 1;
                    break;
                case 'student':
                default:
                    $numRole = 5;
            }
            // @TODO make UTC
            $time = date('Y-m-d h:i:s');
            $ups = "INSERT INTO user (
                firstname, 
                lastname, 
                username, 
                email, 
                password, 
                status, 
                active, 
                auth_source, 
                creator_id,
                registration_date
              ) VALUES (
                '$firstname', 
                '$lastname', 
                '$username', 
                '$email', 
                '$password', 
                $numRole, 
                1, 
                'platform',
                1,
                '$time'
              )";
            $upq = mysql_query($ups);
            $output->writeln('User '.$username.' has been created.');
            if ($isAdmin === 1) {
                $newUserId = mysql_insert_id($upq);
                $uas = "INSERT INTO admin (user_id) values ($newUserId)";
                $uaq = mysql_query($uas);
            }
        } else {
            $output->writeln('A user with username ' . $username . ' already exists');
        }
        return null;
    }
}
