<?php
/**
 * Command functions meant to deal with what the user of this script is calling
 * it for.
 */
/**
 * Namespaces
 */
namespace Chash\Command\User;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class UsersPerUrlAccessCommand
 * Changes the language for all platform users
 * @package Chash\Command\User
 */
class UsersPerUrlAccessCommand extends CommonChamiloUserCommand
{
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('user:url_access')
            ->setAliases(array('urla'))
            ->setDescription('Show the access per Url');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $conn = $this->getConnection($input);

        if ($conn instanceof \Doctrine\DBAL\Connection) {
            $ls = "SELECT url, count(user_id) as users FROM access_url a
                    INNER JOIN access_url_rel_user r ON a.id = r.access_url_id
                    order by url";
            try {
                $stmt = $conn->prepare($ls);
                $stmt->execute();
            } catch (\PDOException $e) {
                $output->writeln('SQL Error!'.PHP_EOL);
                throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
            }
            $output->writeln("Url\t\t\t\t| Number of users");
            while ($lr = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $output->writeln($lr['url'] . "\t\t| " . $lr['users']);
            }
        }
        return null;
    }
}
