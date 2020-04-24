<?php

namespace Chash\Command\Translation;

use Chash\Command\Database\CommonDatabaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DisableLanguageCommand
 * Definition of the translation:disable command
 * Disable a language. Does not support multi-url yet
 * @package Chash\Command\Translation
 */
class DisableLanguageCommand extends CommonDatabaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('translation:disable')
            ->setAliases(array('tdl'))
            ->setDescription('Disables a language, without looking at dependencies (courses and users)')
            ->addArgument(
                'language',
                InputArgument::REQUIRED,
                'The English name for the language to disable.'
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
        $lang = $input->getArgument('language');
        if ($conn instanceof \Doctrine\DBAL\Connection) {
            $langQuoted = $conn->quote($lang);
            $ls = "SELECT id, english_name, available FROM language WHERE english_name = $langQuoted";
            try {
                $stmt = $conn->prepare($ls);
                $stmt->execute();
            } catch (\PDOException $e) {
                $output->write('SQL error!'.PHP_EOL);
                throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
            }
            $num = $stmt->rowCount();
            if ($num < 1) {
                $output->writeln($lang.' language not found in the database. Please make sure you use an existing language name.');
                return null;
            }
            $lr = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($lr['available'] == 0) {
                $output->writeln($lang.' language is already disabled. Nothing to do.');
                return null;
            }
            // Everything is OK so far, enable the language
            $us = "UPDATE language SET available = 0 WHERE id = ".$lr['id'];
            try {
                $uq = $stmt2 = $conn->prepare($us);
                $stmt2->execute();
            } catch (\PDOException $e) {
                $output->write('SQL error!'.PHP_EOL);
                throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
            }
            $output->writeln($langQuoted.' language has been disabled.');
        } else {
            $output->writeln('The connection does not seem to be a valid PDO connection');
        }
        return null;
    }
}
