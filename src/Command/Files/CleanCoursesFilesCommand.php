<?php

namespace Chash\Command\Files;

use Chash\Command\Common\DatabaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Class CleanCoursesFilesCommand
 * Clean the courses directory, leaving only index.html, twig and Serializer.
 */
class CleanCoursesFilesCommand extends DatabaseCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('files:clean_courses_files')
            ->setAliases(['ccf'])
            ->setDescription('Cleans the courses directory');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);
        $this->writeCommandHeader($output, 'Cleaning folders in courses directory.');

        $helper = $this->getHelperSet()->get('question');
        $question = new ConfirmationQuestion(
            '<question>Are you sure you want to clean this Chamilo install\'s courses files? (y/N)</question>',
            false
        );
        if (!$helper->ask($input, $output, $question)) {
            return;
        }
        $files = $this->getConfigurationHelper()->getCoursesFiles();
        $this->removeFiles($files, $output);

        return 0;
    }
}
