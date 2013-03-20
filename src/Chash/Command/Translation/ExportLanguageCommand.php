<?php

namespace Chash\Command\Translation;

use Chash\Command\Database\CommonChamiloDatabaseCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ExportLanguageCommand extends CommonChamiloDatabaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('translation:export_language')
            ->setDescription('Greet someone')
            ->addArgument(
                'language',
                InputArgument::REQUIRED,
                'Which language you want to export'
            )
            ->addOption(
                'tmp',
                null,
                InputOption::VALUE_OPTIONAL,
                'Allows you to specify in which temporary directory the backup files should be placed (optional, defaults to /tmp)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $language  = $input->getArgument('language');
        $tmpFolder = $input->getOption('tmp');

        $_configuration = $this->getHelper('configuration')->getConfiguration();

        $connection = $this->getHelper('configuration')->getConnection();

        if ($connection) {
            $lang = isset($language) ? $language : null;

            $lang = mysql_real_escape_string($lang);

            $q        = mysql_query("SELECT * FROM language WHERE original_name = '$lang' ");
            $langInfo = mysql_fetch_array($q, MYSQL_ASSOC);

            if (!$langInfo) {
                $output->writeln("<comment>Language '$lang' is  not registed in the Chamilo Database</comment>");
                exit;
            } else {
                $output->writeln(
                    "<comment>Language</comment> <info>'$lang'</info> <comment>is registered in the Chamilo installation with iso code: </comment><info>{$langInfo['isocode']} </info>"
                );
            }

            $langFolder = $_configuration['root_sys'].'main/lang/'.$lang;

            if (!is_dir($langFolder)) {
                $output->writeln("<comment>Language '$lang' does not exist in the path: $langFolder</comment>");
            }

            if (empty($tmpFolder)) {
                $tmpFolder = '/tmp/';
                $output->writeln(
                    "<comment>No temporary directory defined. Assuming /tmp/. Please make sure you have *enough space* left on that device"
                );
            }

            if (!is_dir($tmpFolder)) {
                $output->writeln(
                    "<comment>Temporary directory: $tmpFolder is not a valid dir path, using /tmp/ </comment>"
                );
                $tmpFolder = '/tmp/';
            }

            if ($langInfo) {
                $output->writeln("<comment>Creating translation package</comment>");
                $phar = new \PharData($tmpFolder.'lang.tar');
                $phar->buildFromDirectory($langFolder);
                $phar->setMetadata($langInfo);
                $output->writeln("<comment>File created:</comment> <info>{$tmpFolder}lang.tar</info>");
            }
        }
    }
}