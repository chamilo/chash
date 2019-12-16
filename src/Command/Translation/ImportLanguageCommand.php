<?php

namespace Chash\Command\Translation;

use Chash\Command\Common\DatabaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Class ImportLanguageCommand.
 */
class ImportLanguageCommand extends DatabaseCommand
{
    protected function configure(): void
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

    /**
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $helper = $this->getHelperSet()->get('question');
        $_configuration = $this->getHelper('configuration')->getConfiguration();
        $file = $input->getArgument('file');

        $conn = $this->getConnection($input);

        if (is_file($file) && is_readable($file)) {
            $phar = new \PharData($file);
            if ($phar->hasMetadata()) {
                $langInfo = $phar->getMetadata();

                if ($conn instanceof \Doctrine\DBAL\Connection) {
                    $folder = $conn->quote($langInfo['dokeos_folder']);
                    $ls = 'SELECT * FROM language WHERE dokeos_folder = '.$folder;

                    try {
                        $stmt = $conn->prepare($ls);
                        $stmt->execute($ls);
                    } catch (\PDOException $e) {
                        $output->write('SQL error!'.PHP_EOL);

                        throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
                    }
                    $langInfoFromDB = $stmt->fetch(\PDO::FETCH_ASSOC);
                    $langFolderPath = $_configuration['root_sys'].'main/lang/'.$langInfoFromDB['dokeos_folder'];
                    if ($langInfoFromDB && $langFolderPath) {
                        $question = new ConfirmationQuestion(
                            '<question>The '.$langInfo['original_name'].' language already exists in Chamilo. Did you want to overwrite the contents? (y/N)</question>',
                            false
                        );
                        if (!$helper->ask($input, $output, $question)) {
                            return;
                        }
                        if (is_writable($langFolderPath)) {
                            $output->writeln("Trying to save files here: $langFolderPath");
                            $phar->extractTo($langFolderPath, null, true); // extract all files
                            $output->writeln('Files were copied.');
                        } else {
                            $output->writeln(
                                "<error>Make sure that the $langFolderPath folder has writable permissions, or execute the script with sudo </error>"
                            );
                        }
                    } else {
                        //Check if parent_id exists
                        $parentId = '';
                        if (!empty($langInfo['parent_id'])) {
                            $sql = "select selected_value from settings_current where variable = 'allow_use_sub_language'";

                            try {
                                $stmt2 = $conn->prepare($sql);
                                $stmt2->execute();
                            } catch (\PDOException $e) {
                                $output->write('SQL error!'.PHP_EOL);

                                throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
                            }
                            $subLanguageSetting = $stmt2->fetch(\PDO::FETCH_ASSOC);
                            $subLanguageSetting = $subLanguageSetting['selected_value'];
                            if ('true' == $subLanguageSetting) {
                                $sql = 'SELECT * FROM language WHERE id = '.(int) $langInfo['parent_id'];

                                try {
                                    $stmt3 = $conn->prepare($sql);
                                    $stmt3->execute();
                                } catch (\PDOException $e) {
                                    $output->write('SQL error!'.PHP_EOL);

                                    throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
                                }
                                $parentLangInfoFromDB = $stmt3->fetch(\PDO::FETCH_ASSOC);
                                if ($parentLangInfoFromDB) {
                                    $output->writeln(
                                        'Setting parent language: '.$parentLangInfoFromDB['original_name']
                                    );
                                    $parentId = $langInfo['parent_id'];
                                } else {
                                    $output->writeln(
                                        "The lang parent_id = {$langInfo['parent_id']} does not exist in Chamilo. Try to import first the parent language."
                                    );
                                    exit;
                                }
                            } else {
                                $output->writeln(
                                    '<comment>Please turn ON the sublanguage feature in this portal</comment>'
                                );
                                exit;
                            }
                        } else {
                            $output->writeln('Parent language was not provided');
                        }

                        $q = "INSERT INTO language (original_name, english_name, isocode, dokeos_folder, available, parent_id) VALUES (
                                '".$langInfo['original_name']."',
                                '".$langInfo['english_name']."',
                                '".$langInfo['isocode']."',
                                '".$langInfo['dokeos_folder']."',
                                1,
                                '$parentId')";

                        try {
                            $stmt4 = $conn->prepare($q);
                            $stmt4->execute();
                        } catch (\PDOException $e) {
                            $output->write('SQL error!'.PHP_EOL);

                            throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
                        }
                        $output->writeln('Language inserted in the DB');
                        $langFolderPath = $_configuration['root_sys'].'main/lang/'.$langInfo['dokeos_folder'];
                        $phar->extractTo($langFolderPath, null, true); // extract all files
                        $output->writeln("<comment>Files were copied here $langFolderPath </comment>");
                    }
                } else {
                    $output->writeln('The connection does not seem to be a valid PDO connection');
                }
            } else {
                $output->writeln('<comment>The file is not a valid Chamilo language package<comment>');
            }
        } else {
            $output->writeln("<comment>The file located in '$file' is not accessible<comment>");
        }
    }
}
