<?php

namespace Chash\Command\User;

use Chash\Command\Common\ChamiloUserCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class UsersPerUrlAccessCommand
 * Changes the language for all platform users.
 *
 * Command functions meant to deal with what the user of this script is calling it for.
 */
class UsersPerUrlAccessCommand extends ChamiloUserCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('user:url_access')
            ->setAliases(['urla'])
            ->setDescription('Show the accesses users have, per URL, in multi-URL configurations');
    }

    /**
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $conn = $this->getConnection($input);
        $table = $this->getHelperSet()->get('table');

        if ($conn instanceof \Doctrine\DBAL\Connection) {
            $ls = 'SELECT url, count(user_id) as users FROM access_url a
                    INNER JOIN access_url_rel_user r ON a.id = r.access_url_id
                    order by url';

            try {
                $stmt = $conn->prepare($ls);
                $stmt->execute();
            } catch (\PDOException $e) {
                $output->writeln('SQL Error!'.PHP_EOL);

                throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
            }
            $table->setHeaders(['Url', 'Number of Users']);
            $usersPerUrl = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $usersPerUrl[] = [$row['url'], $row['users']];
            }
            $table->setRows($usersPerUrl);
            $table->render($output);
        }

        return null;
    }
}
