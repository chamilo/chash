<?php

namespace Chash\Command\User;

use Chash\Command\Database\CommonDatabaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command functions meant to deal with what the user of this script is calling
 * it for.
 */
/**
 * Makes the given user an admin on the main portal
 */
class MakeAdminCommand extends CommonDatabaseCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('user:make_admin')
            ->setDescription('Makes the given user admin on the main portal')
            ->addArgument(
                'username',
                InputArgument::REQUIRED,
                'Allows you to specify a username to make admin'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $_configuration = $this->getHelper('configuration')->getConfiguration();
        $conn = $this->getConnection($input);
        $username = $input->getArgument('username');
        if ($conn instanceof \Doctrine\DBAL\Connection) {
            try {
                $stmt = $conn->prepare("SELECT id FROM user WHERE username = ".$conn->quote($username));
                $stmt->execute();
                $un = $stmt->rowCount();
            } catch (\PDOException $e) {
                $output->write('SQL error!'.PHP_EOL);
                throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
            }
            if ($un >= 1) {
                try {
                    $user = $stmt->fetch(\PDO::FETCH_OBJ);
                    $stmt2 = $conn->prepare("SELECT id FROM admin WHERE user_id = ".$user->id);
                    $stmt2->execute();
                    $an = $stmt2->rowCount();
                } catch (\PDOException $e) {
                    $output->writeln('Error looking for the user in the admin table.');
                    $output->write('SQL error!'.PHP_EOL);
                    throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
                }
                if ($an < 1) {
                    try {
                        $stmt3 = $conn->prepare("INSERT INTO admin (user_id) VALUES (".$user->id.")");
                        $stmt3->execute();
                    } catch (\PDOException $e) {
                        $output->writeln('Error making '.$username.' an admin.');
                        $output->write('SQL error!'.PHP_EOL);
                        throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
                    }
                    $output->writeln('User '.$username.' is now an admin.');
                } else {
                    $output->writeln('User '.$username.' is already an admin.');
                }
            } else {
                $output->writeln('Could not find user '.$username);
            }
        } else {
            $output->writeln('The connection does not seem to be a valid PDO connection');
        }
        return null;
    }
}
