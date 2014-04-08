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
        $connection = $this->getHelper('configuration')->getConnection();
        $table = $this->getHelperSet()->get('table');
        if (!empty($connection)) {
            $sql = "SELECT url, count(user_id) as users FROM access_url a
                    INNER JOIN access_url_rel_user r ON a.id = r.access_url_id
                    order by url";
            $rs = mysql_query($sql);
            if ($rs === false) {
                $output->writeln('Error in query: '.mysql_error());
                return null;
            } else {
                $table->setHeaders(array('Url', 'Number of Users'));
                $usersPerUrl = array();
                while ($row = mysql_fetch_assoc($rs)) {
                    $usersPerUrl[] = array($row['url'], $row['users']);
                }
                $table->setRows($usersPerUrl);
                $table->render($output);
            }
        }
        return null;
    }
}