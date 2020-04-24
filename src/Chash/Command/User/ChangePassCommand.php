<?php

namespace Chash\Command\User;

use Chash\Command\Database\CommonDatabaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Command functions meant to deal with what the user of this script is calling
 * it for.
 */
/**
 * Class ChangePassCommand
 * Changes a user password to the one given
 * @package Chash\Command\User
 */
class ChangePassCommand extends CommonDatabaseCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('user:change_pass')
            ->setDescription('Updates the user password to the one given')
            ->addArgument(
                'username',
                InputArgument::REQUIRED,
                'Allows you to specify the username'
            )
            ->addArgument(
                'password',
                InputArgument::REQUIRED,
                'The new password to give this user'
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
        $conn = $this->getConnection($input);
        $username = $input->getArgument('username');
        $password = $input->getArgument('password');
        if ($conn instanceof \Doctrine\DBAL\Connection) {
            try {
                $us = "SELECT id FROM user WHERE username = ".$conn->quote($username);
                $stmt = $conn->prepare($us);
                $stmt->execute();
                $un = $stmt->rowCount();
            } catch (\PDOException $e) {
                $output->write('SQL error!'.PHP_EOL);
                throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
            }
            if ($un >= 1) {
                $enc = $_configuration['password_encryption'];
                $salt = sha1(uniqid(null, true));
                switch ($enc) {
                    case 'bcrypt':
                        $password = $conn->quote(password_hash($password, PASSWORD_BCRYPT, ['cost' => 4, 'salt' => $salt]));
                        break;
                    case 'sha1':
                        $password = $conn->quote(sha1($password));
                        break;
                    case 'md5':
                        $password = $conn->quote(md5($password));
                        break;
                    default:
                        $password = $conn->quote($password);
                        break;
                }
                $result = $stmt->fetch(\PDO::FETCH_OBJ);
                try {
                    $ups = "UPDATE user
                      SET password = $password,
                      salt = '$salt'
                      WHERE id = ".$result->id;
                    $stmt = $conn->prepare($ups);
                    $stmt->execute();
                }catch (\PDOException $e) {
                    $output->write('SQL error!'.PHP_EOL);
                    throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
                }
                $output->writeln('User '.$username.' has new password.');
            } else {
                $output->writeln('Could not find user '.$username);
            }
        } else {
            $output->writeln('The connection does not seem to be a valid PDO connection');
        }
        return null;
    }
}
