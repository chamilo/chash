<?php

namespace Chash\Command\Files;

use Chash\Command\Database\CommonChamiloDatabaseCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ShowDiskUsageCommand
 * Show the total disk usage per course compared to the maximum space allowed for the corresponding courses
 * @package Chash\Command\Files
 */
class ShowDiskUsageCommand extends CommonChamiloDatabaseCommand
{
    /**
     *
     */
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('files:show_disk_usage')
            ->setAliases('fsdu')
            ->setDescription('Shows the disk usage vs allowed space, per course');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool|int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $this->writeCommandHeader($output, 'Checking courses dir...');

        $dialog = $this->getHelperSet()->get('dialog');

        if (!$dialog->askConfirmation(
            $output,
            '<question>This operation can take several hours on large volumes. Continue? (y/N)</question>',
            false
        )
        ) {
            return;
        }

        $coursesPath = $this->getConfigurationHelper()->getSysPath();
        $this->getConfigurationHelper()->getConnection();
        $_configuration = $this->getConfigurationHelper()->getConfiguration();
        $courseTable = $_configuration['main_database'].'.course';
        $sql = 'SELECT code, directory, disk_quota from '.$courseTable;
        $res = mysql_query($sql);
        $courses = array();
        if ($res && mysql_num_rows($res) > 0) {
            while ($row = mysql_fetch_assoc($res)) {
                if (!empty($row['directory'])) {
                    $courses[$row['directory']] = array('code' => $row['code'], 'quota' => $row['disk_quota']);
                    $output->writeln($row['directory']);
                }
            }
        }

        $output->writeln($coursesPath);
        $dirs = $this->getConfigurationHelper()->getDataFolders(1);
        $totalSize = 0;
        $finalList = array();
        $orphanList = array();
        if (count($dirs) > 0) {
            $output->writeln('Code;Size(KB);Quota(KB);UsedRatio');
            foreach ($dirs as $dir) {
                //$output->writeln($dir->getRealpath());
                $res = exec('du -s '.$dir->getRealPath());
                $res = preg_split('/\s/',$res);
                $size = $res[0];
                $file = $dir->getFileName();
                //$output->writeln($file);
                $totalSize += $size;
                if (isset($courses[$file]['code'])) {
                    $code = $courses[$file]['code'];
                    $quota = round($courses[$file]['quota']/1024, 0);
                    $rate = '-';
                    if ($quota > 0) {
                        $rate = round(($size/$quota)*100, 0);
                    }
                    $finalList[$code] = array(
                        'code'  => $code,
                        'dir'   => $file,
                        'size'  => $size,
                        'quota' => $quota,
                        'rate'  => $rate,
                    );
                    $output->writeln($code.';'.$size.';'.$finalList[$code]['quota'].';'.$rate);
                } else {
                    $orphanList[$file] = array('size' => $size);
                }
            }
        }
        if (count($orphanList) > 0) {
            foreach($orphanList as $key => $orphan) {
                $output->writeln('ORPHAN-DIR:'.$key.';'.$size.';;;');
            }
        }
        $output->writeln('Total size: '.$totalSize);
    }
}
