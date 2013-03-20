<?php

namespace Chash\Command\Translation;

use Chash\Command\Database\CommonChamiloDatabaseCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImportLanguageCommand extends CommonChamiloDatabaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('translation:import_language')
            ->setDescription('Import a Chamilo language package')
            ->addArgument(
                'file',
                InputArgument::REQUIRED,
                'Path of the language package'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $dialog = $this->getHelperSet()->get('dialog');

        $_configuration = $this->getHelper('configuration')->getConfiguration();

        $file = $input->getArgument('file');

        $connection = $this->getHelper('configuration')->getConnection();

        if (is_file($file) && is_readable($file)) {
            $phar = new \PharData($file);
            if ($phar->hasMetadata()) {
                $langInfo = $phar->getMetadata();

                $connection = $this->getHelper('configuration')->getConnection();
                if ($connection) {
                    $q              = mysql_query(
                        "SELECT * FROM language WHERE dokeos_folder = '{$langInfo['dokeos_folder']}' "
                    );
                    $langInfoFromDB = mysql_fetch_array($q, MYSQL_ASSOC);
                    $langFolderPath = $_configuration['root_sys'].'main/lang/'.$langInfoFromDB['dokeos_folder'];
                    if ($langInfoFromDB && $langFolderPath) {
                        //Overwrite lang files
                        if (!$dialog->askConfirmation(
                            $output,
                            '<question>The '.$langInfo['original_name'].' language already exists in Chamilo. Did you want to overwrite the contents? (y/N)</question>',
                            false
                        )
                        ) {
                            return;
                        }
                        $phar->extractTo($langFolderPath, null, true); // extract all files
                        $output->writeln("Files were copied here $langFolderPath");
                    } else {
                        //Check if parent_id exists
                        $q                    = mysql_query(
                            "SELECT * FROM language WHERE id = '{$langInfo['parent_id']}' "
                        );
                        $parentLangInfoFromDB = mysql_fetch_array($q, MYSQL_ASSOC);
                        if ($parentLangInfoFromDB) {
                            $output->writeln("Setting parent language: ".$parentLangInfoFromDB['original_name']);

                            $q = mysql_query(
                                "INSERT INTO language (original_name, english_name, isocode, dokeos_folder, available, parent_id) VALUES (
                                                                            '".$langInfo['original_name']."',
                                            '".$langInfo['english_name']."',
                                            '".$langInfo['isocode']."',
                                            '".$langInfo['dokeos_folder']."',
                                            '1',
                                            '".$langInfo['parent_id']."')"
                            );
                            if ($q) {
                                $output->writeln("Language inserted in the DB");
                                $langFolderPath = $_configuration['root_sys'].'main/lang/'.$langInfo['dokeos_folder'];
                                $phar->extractTo($langFolderPath, null, true); // extract all files
                                $output->writeln("Files were copied here $langFolderPath");
                            }
                        } else {
                            $output->writeln(
                                "The lang parent_id = {$langInfo['parent_id']} does not exist in Chamilo. Try to import first the parent."
                            );
                        }

                    }
                }

            } else {
                $output->writeln("<comment>The file is not a valid Chamilo language package<comment>");
            }
        } else {
            $output->writeln("<comment>The file located in '$file' is not accessible<comment>");
        }

    }
}