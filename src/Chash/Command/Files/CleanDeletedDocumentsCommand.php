<?php

namespace Chash\Command\Files;

use Chash\Command\Database\CommonChamiloDatabaseCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CleanDeletedDocmentsCommand
 * Clean the courses/[CODE]/documents/ directory, removing all documents and folders marked DELETED
 * @package Chash\Command\Files
 */
class CleanDeletedDocumentsCommand extends CommonChamiloDatabaseCommand
{
    /**
     *
     */
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('files:clean_deleted_documents')
            ->setDescription('Cleans the documents that were deleted but left as _DELETED_');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool|int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->writeCommandHeader($output, 'Cleaning deleted documents.');

        $dialog = $this->getHelperSet()->get('dialog');

        if (!$dialog->askConfirmation(
            $output,
            '<question>Are you sure you want to clean the Chamilo deleted documents? (y/N)</question>',
            false
        )
        ) {
            return;
        }

        $files = $this->getConfigurationHelper()->getDeletedDocuments();
        print_r($files);
        $this->removeFiles($files, $output);
    }
}
