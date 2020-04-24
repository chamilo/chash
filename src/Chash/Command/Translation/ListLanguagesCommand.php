<?php

namespace Chash\Command\Translation;

use Chash\Command\Database\CommonDatabaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ListLanguagesCommand
 * Definition of the translation:list command
 * Definition of command to list platform languages
 * @package Chash\Command\Translation
 */
class ListLanguagesCommand extends CommonDatabaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('translation:list')
            ->setAliases(array('tl'))
            ->setDescription('Gets all languages as a list')
            ->addArgument(
                'availability',
                InputArgument::OPTIONAL,
                'Filter the availability we want (0 for disabled, 1 for enabled, empty for all).'
            )
            ->addArgument(
                'count',
                InputArgument::OPTIONAL,
                'Show the number of users currently using each language.'
            );
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
        $conn = $this->getConnection($input);
        $availability = $input->getArgument('availability');
        $current = 'english';
        $count = $input->getArgument('count');
        if ($conn instanceof \Doctrine\DBAL\Connection) {
            $ls = "SELECT selected_value FROM settings_current WHERE variable='platformLanguage'";
            try {
                $stmt = $conn->prepare($ls);
                $stmt->execute();
            } catch (\PDOException $e) {
                $output->write('SQL error!'.PHP_EOL);
                throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
            }
            $lr = $stmt->fetch(\PDO::FETCH_ASSOC);
            //$output->writeln('Current default language is: '.$lr['selected_value']);
            $current = $lr['selected_value'];
            $where = '';
            if ($availability === '0') {
                $where = 'WHERE available = 0';
            } elseif ($availability === '1') {
                $where = 'WHERE available = 1';
            }
            $ls = "SELECT english_name, available FROM language ".$where." ORDER BY english_name";
            try {
                $stmt = $conn->prepare($ls);
                $stmt->execute();
            } catch (\PDOException $e) {
                $output->write('SQL error!'.PHP_EOL);
                throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
            }
            $titleLine = "Language          | Enabled | Platform language";
            $titleLine2 = "-----------------------------------------------";
            if ($count > 0) {
                $titleLine .= " | Users";
                $titleLine2 .= "--------";
            }
            $output->writeln($titleLine);
            $output->writeln($titleLine2);
            while ($lr = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $pl = ' ';
                $l = strlen($lr['english_name']);
                if ($lr['english_name'] == $current) {
                    $pl = '*';
                }
                $resultLine = $lr['english_name'].str_repeat(' ', 18 - $l)."| ".$lr['available']."       | ".$pl;
                if ($count > 0) {
                    $language = $conn->quote($lr['english_name']);
                    $countUsers = "SELECT count(*) as num FROM user WHERE language = $language";
                    try {
                        $stmt2 = $conn->prepare($countUsers);
                        $stmt2->execute();
                    } catch (\PDOException $e) {
                        $output->write('SQL error!'.PHP_EOL);
                        throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
                    }
                    $countUsersNum = $stmt2->fetch(\PDO::FETCH_ASSOC);
                    $resultLine .= "                 | ".$countUsersNum['num'];
                }
                $output->writeln($resultLine);
            }
        }
        return null;
    }
}
