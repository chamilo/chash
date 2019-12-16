<?php

namespace Chash\Command\Files;

use Chash\Command\Common\DatabaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class GenerateTempFileStructureCommand.
 */
class GenerateTempFileStructureCommand extends DatabaseCommand
{
    /**
     * @param array $files
     * @param $permission
     *
     * @return int|null
     */
    public function createFolders(OutputInterface $output, $files, int $permission): ?int
    {
        $dryRun = $this->getConfigurationHelper()->getDryRun();

        if (empty($files)) {
            $output->writeln('<comment>No files found.</comment>');

            return 0;
        }

        $fs = new Filesystem();

        try {
            if ($dryRun) {
                $output->writeln('<comment>Folders to be created with permission '.decoct($permission).':</comment>');
                foreach ($files as $file) {
                    $output->writeln($file);
                }
            } else {
                $output->writeln('<comment>Creating folders with permission '.decoct($permission).':</comment>');
                foreach ($files as $file) {
                    $output->writeln($file);
                }
                $fs->mkdir($files, $permission);
            }
        } catch (IOException $e) {
            echo "\n An error occurred while removing the directory: ".$e->getMessage()."\n ";
        }
    }

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('files:generate_temp_folders')
            ->setDescription('Generate temp folder structure: twig');
    }

    /**
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);
        $this->writeCommandHeader($output, 'Generating temp folders.');

        // Data folders
        $files = $this->getConfigurationHelper()->getTempFolderList();
        $this->createFolders($output, $files, 0777);

        return 0;
    }
}
