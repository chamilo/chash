<?php

namespace Chash\Command\Translation;

use Chash\Command\Common\DatabaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ExportLanguageCommand.
 */
class ExportLanguageCommand extends DatabaseCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('translation:export_language')
            ->setDescription('Exports a Chamilo language package in .tar in /tmp/[language].tar or somewhere else')
            ->addArgument(
                'language',
                InputArgument::REQUIRED,
                'Which language you want to export'
            )
            ->addOption(
                'tmp',
                null,
                InputArgument::OPTIONAL,
                'Allows you to specify in which temporary directory the backup files should be placed (optional, defaults to /tmp)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        $language = $input->getArgument('language');
        $tmpFolder = $input->getOption('tmp');

        $_configuration = $this->getHelper('configuration')->getConfiguration();

        $conn = $this->getConnection($input);

        if ($conn instanceof \Doctrine\DBAL\Connection) {
            $lang = isset($language) ? $language : null;
            $langQuoted = $conn->quote($lang);

            $q = "SELECT * FROM language WHERE english_name = $langQuoted ";

            try {
                $stmt = $conn->prepare($q);
                $stmt->execute();
            } catch (\PDOException $e) {
                $output->write('SQL error!'.PHP_EOL);

                throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
            }
            $langInfo = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$langInfo) {
                $output->writeln("<comment>Language $langQuoted is not registered in the Chamilo Database</comment>");

                $q = 'SELECT * FROM language WHERE parent_id IS NULL or parent_id = 0';

                try {
                    $stmt2 = $conn->prepare($q);
                    $stmt2->execute();
                } catch (\PDOException $e) {
                    $output->write('SQL error!'.PHP_EOL);

                    throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
                }
                $output->writeln('<comment>Available languages are: </comment>');
                $list = '';
                while ($langRow = $stmt2->fetch(\PDO::FETCH_ASSOC)) {
                    $list .= $langRow['english_name'].', ';
                }
                $output->write(substr($list, 0, -2));
                $output->writeln(' ');

                $q = 'SELECT * FROM language WHERE parent_id <> 0';

                try {
                    $stmt3 = $conn->prepare($q);
                    $stmt3->execute();
                } catch (\PDOException $e) {
                    $output->write('SQL error!'.PHP_EOL);

                    throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
                }
                $output->writeln('<comment>Available sub languages are: </comment>');
                $list = '';
                while ($langRow = $stmt3->fetch(\PDO::FETCH_ASSOC)) {
                    $list .= $langRow['english_name'].', ';
                }
                $output->write(substr($list, 0, -2));
                $output->writeln(' ');
                exit;
            } else {
                $output->writeln(
                    "<comment>Language</comment> <info>$langQuoted</info> <comment>is registered in the Chamilo installation with iso code: </comment><info>{$langInfo['isocode']} </info>"
                );
            }

            $langFolder = $_configuration['root_sys'].'main/lang/'.$lang;

            if (!is_dir($langFolder)) {
                $output->writeln("<comment>Language $langQuoted does not exist in the path: $langFolder</comment>");
            }

            if (empty($tmpFolder)) {
                $tmpFolder = '/tmp/';
                $output->writeln(
                    '<comment>No temporary directory defined. Assuming /tmp/. Please make sure you have *enough space* left on that device'
                );
            }

            if (!is_dir($tmpFolder)) {
                $output->writeln(
                    "<comment>Temporary directory: $tmpFolder is not a valid dir path, using /tmp/ </comment>"
                );
                $tmpFolder = '/tmp/';
            }

            if ($langInfo) {
                $output->writeln('<comment>Creating translation package</comment>');
                $fileName = $tmpFolder.$langInfo['english_name'].'.tar';
                $phar = new \PharData($fileName);
                $phar->buildFromDirectory($langFolder);

                $phar->setMetadata($langInfo);
                $output->writeln("<comment>File created:</comment> <info>{$fileName}</info>");
            }
        } else {
            $output->writeln('The connection does not seem to be a valid PDO connection');
        }
    }
}
