<?php

namespace Chash\Command\Files;

use Chash\Command\Database\CommonDatabaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;


/**
 * Class RestoreDeletedDocumentsCommand
 * Restore the courses/[CODE]/documents/ directory, restoring all documents
 * and folders marked DELETED.
 * Note: although deleting a folder will rename the folder *and* its contents
 * on disk, this is not the case in the database. In the database, the folder
 * contents have their filename renamed, but not the path (including that
 * folder name). This explains the weird things we do below, using some names
 * from the database, and some filenames from the disk.
 * @package Chash\Command\Files
 */
class RestoreDeletedDocumentsCommand extends CommonDatabaseCommand
{
    /**
     * Configure the command, define the options available
     */
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('files:restore_deleted_documents')
            ->setAliases(array('frdd'))
            ->setDescription('Restores the documents that were deleted but left as _DELETED_ in this course (including all sessions)')
            ->addOption(
                'course',
                null,
                InputOption::VALUE_REQUIRED,
                'Only restore items from the given course code'
            )
            ->addOption(
                'list',
                null,
                InputOption::VALUE_NONE,
                'Show the complete list of files to be restored before asking for confirmation'
            )
        ;
    }

    /**
     * Searches for the deleted documents and tries to restore them.
     * This method does NOT: 1) restore search engine indexed docs, 2) restore the template status of HTML documents
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool|int|null|void
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $course = $input->getOption('course');
        if (empty($course)) {
            $output->writeln('No course code provided. This command cannot be run on all courses at once.');
            return;
        }
        $connection = $this->getConnection($input);
        $sql = "SELECT id, directory FROM course WHERE code = '$course'";
        $stmt = $connection->executeQuery($sql);
        $row = $stmt->fetchAssociative();
        $courseDir = $row['directory'];
        $courseId = $row['id'];
        try {
            $files = @$this->getConfigurationHelper()->getDeletedDocuments([$courseDir]);
        } catch(\Exception $e) {
            $output->writeln('Exception triggered when getting list of deleted documents on disk');
            return;
        }
        // Make sure all files appear first. This is necessary to avoid issues
        // when renaming folders which contain files
        $filesFirst = [];
        $foldersSecond = [];
        foreach ($files as $file) {
            if (is_file($file->getRealPath())) {
                $filesFirst[] = $file->getRealPath();
            } elseif (is_dir($file->getRealPath())) {
                $foldersSecond[] = $file->getRealPath();
            }
        }
        $files = [];
        foreach ($filesFirst as $file) {
            $files[] = $file;
        }
        $foldersSecond = $this->_deeperFirst($foldersSecond);
        foreach ($foldersSecond as $folder) {
            $files[] = $folder;
        }
        $connection = $this->getConnection($input);

        if ($input->isInteractive()) {
            $this->writeCommandHeader($output, 'Restoring deleted documents.');
            $list = $input->getOption('list'); //1 if the option was set
            if ($list) {
                if (count($files) > 0) {
                    foreach ($files as $file) {
                        $output->writeln('Would restore '.$file);
                    }
                } else {
                    $output->writeln('No file to be restored in course/'.$courseDir.'/document/ directory');
                    return;
                }
            } else {
                if (count($files) > 0) {
                    foreach ($files as $file) {
                        $output->writeln('Restoring '.$file);
                        $this->_restoreDocument($connection, $courseId, $courseDir, $file);
                    }
                    $output->writeln(
                        'Restored all database references in c_document. Table c_item_property updated to remove delete action.'
                    );
                }
            }
        }
    }

    /**
     * Restores one file or folder marked deleted in a course
     */
    private function _restoreDocument(
        \Doctrine\DBAL\Connection $connection,
        int $courseId,
        string $courseDirectory,
        string $deletedFilePath
    ): bool
    {
        $matches = [];
        // Remove the _DELETED_xyz part as the restored path. The last part is the document's iid.
        preg_match('#(.*)_DELETED_(\d+)$#', $deletedFilePath, $matches);
        $restoredFilePath = $matches[1];
        $documentId = $matches[2];

        $sql = "SELECT iid, filetype, path FROM c_document WHERE iid = $documentId";
        $stmt = $connection->executeQuery($sql);
        $row = $stmt->fetchAssociative();
        $isFolder = false;
        if (empty($row)) {
            return false;
        }
        if ($row['filetype'] == 'folder') {
            $isFolder = true;
        }
        $deletedDBFilePath = $row['path'];
        $matches = [];
        // Get the restored path using the path from the database, which
        // doesn't include the renamed folder (while the filesystem path
        // contains it).
        preg_match('#(.*)_DELETED_(\d+)$#', $deletedDBFilePath, $matches);
        $restoredDBFilePath = $matches[1];
        $fs = new Filesystem();

        // 4 steps needed to recover the file:
        // 1. rename the file on disk,
        // 2. delete the "delete" edit in c_item_property,
        // 3. update the last action to 'DocumentVisible' and visibility to 1,
        // 4. update the path of the file in c_document
        // For folders, this relies on other processes taking care of children first, but the update of paths in c_document still has to go through all possible children
        // 1. Rename the file on disk
        if (file_exists($deletedFilePath)) {
            try {
                $fs->rename($deletedFilePath, $restoredFilePath);
            } catch (\Exception $e) {
                return false;
            }
        } else {
            return false;
        }
        // 2. delete the "delete" edit action in c_item_property,
        $sql = "DELETE FROM c_item_property WHERE tool = 'document' AND ref = $documentId AND lastedit_type = 'delete'";
        $stmt = $connection->executeQuery($sql);
        // 3. update the last action to 'DocumentVisible' and visibility to 1,
        $sql = "UPDATE c_item_property
            SET lastedit_type = 'DocumentVisible', visibility = 1 
            WHERE tool = 'document' AND ref = $documentId AND lastedit_type = 'DocumentDeleted'";
        $stmt = $connection->executeQuery($sql);
        // 4. update the path of the file in c_document
        if ($isFolder) {
            // multi change (change all things that have the folder name in their path)
            $sql = "UPDATE c_document SET path = REPLACE(path, '$deletedDBFilePath', '$restoredDBFilePath') WHERE c_id = $courseId AND path LIKE '$deletedDBFilePath%'";
            $stmt = $connection->executeQuery($sql);
        } else {
            $sql = "UPDATE c_document SET path = '$restoredDBFilePath' WHERE iid = $documentId";
            $stmt = $connection->executeQuery($sql);
        }

        return true;
    }

    /**
     * Method to get deepest folders first.
     * Probably many ways to optimize algorithmically, but not really useful in context.
     * @param array $folders
     * @return array
     */
    private function _deeperFirst(array $folders)
    {
        $local = [];
        // Sort folders by level of depth
        foreach ($folders as $folderPath) {
            $list = preg_split('#/#', $folderPath);
            $depth = count($list)-1;
            if (isset($local[$depth]) && is_array($local[$depth])) {
                $local[$depth][] = $folderPath;
            } else {
                $local[$depth] = [];
                $local[$depth][] = $folderPath;
            }
        }
        // Sort again to have deepest folders first
        $localClean = [];
        foreach ($local as $level => $list) {
            if (!isset($local[$level]) || !is_array($local[$level])) {
                continue;
            }
            if (count($local[$level]) > 0) {
                array_unshift($localClean, $list);
            }
        }
        // Flatten the array into a single-dimension array
        $localLevelledOut = [];
        foreach ($localClean as $reverseLevel => $list) {
            foreach ($list as $folderPath) {
                $localLevelledOut[] = $folderPath;
            }
        }

        return $localLevelledOut;
    }
}
