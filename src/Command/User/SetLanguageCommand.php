<?php

namespace Chash\Command\User;

use Chash\Command\Common\DatabaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class SetLanguageCommand
 * Changes the language for all platform users
 * Command functions meant to deal with what the user of this script is calling
 * it for.
 */
class SetLanguageCommand extends DatabaseCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('user:set_language')
            ->setAliases(['usl'])
            ->setDescription('Sets all the users\' language to the one given')
            ->addArgument(
                'language',
                InputArgument::OPTIONAL,
                'The English name for the new language to set all users to'
            )
            ->addArgument(
                'username',
                InputArgument::OPTIONAL,
                'The username of one user to change the language for. If not provided, changes all users'
            );
    }

    /**
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $_configuration = $this->getHelper('configuration')->getConfiguration();
        $conn = $this->getConnection($input);
        $lang = $input->getArgument('language');
        $username = $input->getArgument('username');
        if ($conn instanceof \Doctrine\DBAL\Connection) {
            if (empty($lang)) {
                try {
                    $ls = "SELECT DISTINCT language, count(*) as num FROM user GROUP BY 1 ORDER BY language";
                    $stmt = $conn->prepare($ls);
                    $stmt->execute();
                } catch (\PDOException $e) {
                    $output->writeln('SQL Error!'.PHP_EOL);
                    throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
                }
                $output->writeln("Language\t| Number of users");
                while ($lr = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $output->writeln($lr['language']."\t\t| ".$lr['num']);
                }
            } else {
                try {
                    // Check available languages
                    $ls = "SELECT english_name FROM language ORDER BY english_name";
                    $stmt = $conn->prepare($ls);
                    $stmt->execute();
                } catch (\PDOException $e) {
                    $output->writeln('SQL Error!'.PHP_EOL);
                    throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
                }
                $languages = [];
                while ($lr = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $languages[] = $lr['english_name'];
                }
                if (!in_array($lang, $languages)) {
                    $output->writeln($lang.' must be available on your platform before you can use it');

                    return null;
                }
                if (empty($username)) {
                    try {
                        $lang = $conn->quote($lang);
                        $lu = "UPDATE user SET language = $lang";
                        $stmt = $conn->prepare($lu);
                        $stmt->execute();
                    } catch (\PDOException $e) {
                        $output->writeln('SQL Error!'.PHP_EOL);
                        throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
                    }
                    $output->writeln('Language set to '.$lang.' for all users');
                } else {
                    try {
                        $lang = $conn->quote($lang);
                        $username = $conn->quote($username);
                        $lu = "UPDATE user SET language = $lang WHERE username = $username";
                        $stmt = $conn->prepare($lu);
                        $stmt->execute();
                    } catch (\PDOException $e) {
                        $output->writeln('SQL Error!'.PHP_EOL);
                        throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
                    }
                    $output->writeln('Language set to '.$lang.' for user '.$username);
                }
            }
        }

        return null;
    }
}
