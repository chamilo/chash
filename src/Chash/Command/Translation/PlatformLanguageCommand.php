<?php
/**
 * Definition of command to
 * change platform language
 * Does not support multi-url yet.
 */
/**
 * Necessary namespaces definitions and usage.
 */

namespace Chash\Command\Translation;

use Chash\Command\Database\CommonDatabaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class PlatformLanguageCommand
 * Definition of the translation:platform_language command.
 */
class PlatformLanguageCommand extends CommonDatabaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('translation:platform_language')
            ->setAliases(['tpl'])
            ->setDescription('Gets or sets the platform language')
            ->addArgument(
                'language',
                InputArgument::OPTIONAL,
                'Which language you want to set (English name). Leave empty to get current language.'
            );
    }

    /**
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $_configuration = $this->getHelper('configuration')->getConfiguration();
        $conn = $this->getConnection($input);
        $lang = $input->getArgument('language');
        if ($conn instanceof \Doctrine\DBAL\Connection) {
            if (empty($lang)) {
                $ls = "SELECT selected_value FROM settings_current WHERE variable='platformLanguage'";
                try {
                    $stmt = $conn->prepare($ls);
                    $stmt->execute();
                } catch (\PDOException $e) {
                    $output->write('SQL error!'.PHP_EOL);
                    throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
                }
                $lr = $stmt->fetch(\PDO::FETCH_ASSOC);
                $output->writeln('Current default language is: '.$lr['selected_value']);
            } else {
                $ls = "SELECT english_name FROM language ORDER BY english_name";
                try {
                    $stmt = $conn->prepare($ls);
                    $stmt->execute();
                } catch (\PDOException $e) {
                    $output->write('SQL error!'.PHP_EOL);
                    throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
                }
                $languages = [];
                while ($lr = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $languages[] = $lr['english_name'];
                }
                if (!in_array($lang, $languages)) {
                    $output->writeln($lang.' must be available on your platform before you can set it as default');

                    return null;
                }
                $lang = $conn->quote($lang);
                $lu = "UPDATE settings_current set selected_value = $lang WHERE variable = 'platformLanguage'";
                try {
                    $stmt = $conn->prepare($lu);
                    $stmt->execute();
                } catch (\PDOException $e) {
                    $output->write('SQL error!'.PHP_EOL);
                    throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
                }
                $output->writeln('Language set to '.$lang);
            }
        }

        return null;
    }
}
