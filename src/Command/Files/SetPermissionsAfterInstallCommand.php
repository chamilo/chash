<?php

namespace Chash\Command\Files;

use Chash\Command\Common\DatabaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class SetPermissionsAfterInstallCommand.
 */
class SetPermissionsAfterInstallCommand extends DatabaseCommand
{
    /**
     * @param array $files
     * @param $permission
     * @param $user
     * @param $group
     * @param bool $listFiles
     * @param int|null $permission
     * @param bool|null|string|string[] $user
     * @param bool|null|string|string[] $group
     *
     * @return int|null
     */
    public function setPermissions(
        OutputInterface $output,
        $files,
        ?int $permission,
        $user,
        $group,
        $listFiles = true
    ): ?int {
        $dryRun = $this->getConfigurationHelper()->getDryRun();

        if (empty($files)) {
            $output->writeln('<comment>No files found.</comment>');

            return 0;
        }

        $fs = new Filesystem();

        try {
            if ($dryRun) {
                $output->writeln('<comment>Modifying files permission to: '.decoct($permission).'</comment>');
                $output->writeln('<comment>user: '.$user.'</comment>');
                $output->writeln('<comment>group: '.$group.'</comment>');
                if ($listFiles) {
                    $output->writeln('<comment>Files: </comment>');
                    foreach ($files as $file) {
                        $output->writeln($file->getPathName());
                    }
                }
            } else {
                if (!empty($permission)) {
                    $output->writeln('<comment>Modifying files permission to: '.decoct($permission).'</comment>');
                }
                if (!empty($user)) {
                    $output->writeln('<comment>Modifying file user: '.$user.'</comment>');
                }
                if (!empty($group)) {
                    $output->writeln('<comment>Modifying file group: '.$group.'</comment>');
                }

                if ($listFiles) {
                    $output->writeln('<comment>Files: </comment>');
                    foreach ($files as $file) {
                        $output->writeln($file->getPathName());
                    }
                } else {
                    $output->writeln('<comment>Skipping file list (too long)... </comment>');
                }

                if (!empty($permission)) {
                    foreach ($files as $file) {
                        if ($fs->exists($file)) {
                            $fs->chmod($file, $permission, 0000, true);
                        }
                    }
                }

                if (!empty($user)) {
                    //$fs->chown($files, $user, true);
                }

                if (!empty($group)) {
                    //$fs->chgrp($files, $group, true);
                }
            }
        } catch (IOException $e) {
            echo "\n An error occurred while removing the directory: ".$e->getMessage()."\n ";
        }
    }

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('files:set_permissions_after_install')
            ->setDescription('Set permissions')
            ->addOption('linux-user', null, InputOption::VALUE_OPTIONAL, 'user', 'www-data')
            ->addOption('linux-group', null, InputOption::VALUE_OPTIONAL, 'group', 'www-data');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);
        $this->writeCommandHeader($output, 'Setting permissions ...');

        $linuxUser = $input->getOption('linux-user');
        $linuxGroup = $input->getOption('linux-group');

        // All files
        $this->writeCommandHeader($output, 'System folders...');
        $files = $this->getConfigurationHelper()->getSysFolders();
        $this->setPermissions($output, $files, 0777, $linuxUser, $linuxGroup, false);

        $this->writeCommandHeader($output, 'System files ...');
        $files = $this->getConfigurationHelper()->getSysFiles();
        $this->setPermissions($output, $files, null, $linuxUser, $linuxGroup, false);

        // Data folders
        $this->writeCommandHeader($output, 'Data folders ...');
        $files = $this->getConfigurationHelper()->getDataFolders();
        $this->setPermissions($output, $files, 0777, $linuxUser, $linuxGroup, false);

        // Config folders
        $this->writeCommandHeader($output, 'Config folders ...');
        $files = $this->getConfigurationHelper()->getConfigFolders();
        $this->setPermissions($output, $files, 0555, $linuxUser, $linuxGroup, false);

        $this->writeCommandHeader($output, 'Config files...');
        $files = $this->getConfigurationHelper()->getConfigFiles();
        $this->setPermissions($output, $files, 0555, $linuxUser, $linuxGroup, false);

        // Temp folders
        $this->writeCommandHeader($output, 'Temp files...');
        $files = $this->getConfigurationHelper()->getTempFolders();
        $this->setPermissions($output, $files, 0777, $linuxUser, $linuxGroup, false);

        return 0;
    }
}
