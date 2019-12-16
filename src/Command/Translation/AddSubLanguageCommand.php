<?php

namespace Chash\Command\Translation;

use Chash\Command\Common\DatabaseCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AddSubLanguageCommand
 * Definition of the translation:add_sub_language command
 * Does not support multi-url yet.
 */
class AddSubLanguageCommand extends DatabaseCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('translation:add_sub_language')
            ->setAliases(['tasl'])
            ->setDescription('Creates a sub-language')
            ->addArgument(
                'parent',
                InputArgument::REQUIRED,
                'The parent language (English name) for the new sub-language.'
            )
            ->addArgument(
                'sublanguage',
                InputArgument::REQUIRED,
                'The English name for the new sub-language.'
            );
    }

    /**
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);
        $_configuration = $this->getConfigurationArray();
        $conn = $this->getConnection($input);

        $parent = $input->getArgument('parent');
        $lang = $input->getArgument('sublanguage');
        if ($conn instanceof \Doctrine\DBAL\Connection) {
            $langQuoted = $conn->quote($lang);
            $sql = "SELECT english_name FROM language WHERE english_name = $langQuoted";

            try {
                $stmt = $conn->prepare($sql);
                $stmt->execute();
            } catch (\PDOException $e) {
                $output->write('SQL error!'.PHP_EOL);

                throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
            }

            $count = $stmt->rowCount();
            if ($count) {
                $output->writeln($lang.' already exists in the database. Pick another English name.');

                return null;
            }

            $parentQuoted = $conn->quote($parent);
            $sql = "SELECT id, original_name, english_name, isocode, dokeos_folder
                    FROM language WHERE english_name = $parentQuoted";

            try {
                $stmt2 = $conn->prepare($sql);
                $stmt2->execute();
            } catch (\PDOException $e) {
                $output->write('SQL error!'.PHP_EOL);

                throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
            }
            $count = $stmt2->rowCount();
            $parentData = $stmt2->fetch();

            if ($count < 1) {
                $output->writeln("The parent language $parentQuoted does not exist. Please choose a valid parent.");

                return null;
            }

            if (is_dir($_configuration['root_sys'].'main/lang/'.$lang)) {
                $output->writeln('The destination directory ('.$_configuration['root_sys'].'main/lang/'.$lang.') already exists. Please choose another sub-language name.');

                return null;
            }

            // Everything is OK so far, insert the sub-language
            try {
                $conn->insert('language', [
                    'original_name' => $parentData['original_name'].'-2',
                    'english_name' => $lang,
                    'isocode' => $parentData['isocode'],
                    'dokeos_folder' => $lang,
                    'available' => 0,
                    'parent_id' => $parentData['id'],
                ]);
            } catch (\PDOException $e) {
                $output->write('SQL error!'.PHP_EOL);

                throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
            }
            //Permissions gathering, copied from main_api.lib.php::api_get_permissions_for_new_directories()
            //require_once $_configuration['root_sys'].'main/inc/lib/main_api.lib.php';
            //$perm = api_get_permissions_for_new_directories();
            // @todo Improve permissions to force creating as user www-data
            $r = @mkdir($_configuration['root_sys'].'main/lang/'.$lang, 0777);
            $output->writeln('Sub-language '.$lang.' of language '.$parent.' has been created but is disabled. Fill it, then enable to make available to users. Make sure you check the permissions for the newly created directory as well ('.$_configuration['root_sys'].'main/lang/'.$lang.')');
        } else {
            $output->writeln('The connection does not seem to be a valid PDO connection');
        }

        return null;
    }
}
