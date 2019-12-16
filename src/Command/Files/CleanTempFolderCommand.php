<?php

namespace Chash\Command\Files;

use Chash\Command\Common\DatabaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Class CleanTempFolderCommand.
 */
class CleanTempFolderCommand extends DatabaseCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('files:clean_temp_folder')
            ->setAliases(['fct'])
            ->setDescription('Cleans the temp directory.');
    }

    /**
     * @return int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);
        $this->writeCommandHeader($output, 'Cleaning temp files.');
        $helper = $this->getHelperSet()->get('question');
        $question = new ConfirmationQuestion(
            '<question>Are you sure you want to clean the Chamilo temp files? (y/N)</question>',
            true
        );
        if (!$helper->ask($input, $output, $question)) {
            return 0;
        }
        $files = $this->getConfigurationHelper()->getTempFiles();
        $this->removeFiles($files, $output);

        return 0;
    }
}
