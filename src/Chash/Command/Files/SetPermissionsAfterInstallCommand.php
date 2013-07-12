<?php

namespace Chash\Command\Files;

use Chash\Command\Database\CommonChamiloDatabaseCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;


class SetPermissionsAfterInstallCommand extends CommonChamiloDatabaseCommand
{
    /**
     *
     */
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('files:set_permissions_after_install')
            ->setDescription('Set permissions');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $this->writeCommandHeader($output, 'Setting permissions.');

        /*$dialog = $this->getHelperSet()->get('dialog');

        if (!$dialog->askConfirmation(
            $output,
            '<question>Are you sure you want to clean your config files? (y/N)</question>',
            false
        )
        ) {
            return;
        }*/

        // $configuration = $this->getConfigurationArray();

        // Data folders
        $files = $this->getConfigurationHelper()->getDataFolders();
        $this->setPermissions($output, $files, 0777);

        // Config folders
        $files = $this->getConfigurationHelper()->getConfigFolders();
        $this->setPermissions($output, $files, 0555);
        $files = $this->getConfigurationHelper()->getConfigFiles();
        $this->setPermissions($output, $files, 0555);

        // Temp folders
        $files = $this->getConfigurationHelper()->getTempFolders();
        $this->setPermissions($output, $files, 0777);
    }

    /**
     * @param $files
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @return int
     */
    public function setPermissions(\Symfony\Component\Console\Output\OutputInterface $output, $files, $permission)
    {
        $dryRun = $this->getConfigurationHelper()->getDryRun();

        if (empty($files)) {
            $output->writeln('<comment>No files found.</comment>');
            return 0;
        }

        $fs = new Filesystem();
        try {
            if ($dryRun) {
                $output->writeln("<comment>Files to be changed to ".decoct($permission).":</comment>");
                foreach ($files as $file) {
                    $output->writeln($file->getPathName());
                }
            } else {
                $output->writeln("<comment>Changing permissions to  ".decoct($permission).":</comment>");
                foreach ($files as $file) {
                    $output->writeln($file->getPathName());
                }
                $fs->chmod($files, $permission, 0000, true);
            }

        } catch (IOException $e) {
            echo "\n An error occurred while removing the directory: ".$e->getMessage()."\n ";
        }
    }
}
