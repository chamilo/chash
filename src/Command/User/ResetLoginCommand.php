<?php

namespace Chash\Command\User;

use Chash\Command\Common\ChamiloUserCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command functions meant to deal with what the user of this script is calling
 * it for.
 */
/**
 * Class ResetLoginCommand
 * Returns a password reset link for the given username (user will receive
 * an e-mail with new login + password).
 */
class ResetLoginCommand extends ChamiloUserCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('user:reset_login')
            ->setDescription('Outputs login link for given username')
            ->addArgument(
                'username',
                InputArgument::REQUIRED,
                'Allows you to specify a username to login as'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);
        $_configuration = $this->getHelper('configuration')->getConfiguration();
        $username = $input->getArgument('username');
        $conn = $this->getConnection($input);
        if ($conn instanceof \Doctrine\DBAL\Connection) {
            try {
                $us = 'SELECT id, email FROM user WHERE username = '.$conn->quote($username);
                $stmt = $conn->prepare($us);
                $stmt->execute();
            } catch (\PDOException $e) {
                $output->write('SQL error!'.PHP_EOL);

                throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
            }
            $un = $stmt->rowCount();
            if ($un >= 1) {
                $user = $stmt->fetch(\PDO::FETCH_ASSOC);
                $link = $_configuration['root_web'].'main/auth/lostPassword.php?reset=';
                $link .= sha1($user['email']).'&id='.$user['id'];
                $output->writeln('Follow this link to login as '.$username);
                $output->writeln($link);
            } else {
                $output->writeln('Could not find user '.$username);
            }
        } else {
            $output->writeln('The connection does not seem to be a valid PDO connection');
        }

        return null;
    }
}
