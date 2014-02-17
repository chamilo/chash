<?php

namespace Chash\Command\Files;

use Chash\Command\Database\CommonChamiloDatabaseCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class UpdateDirectoryMaxSizeCommand
 * Increase the maximum space allowed on disk progressively. This command is
 * used called once every night, to make a "progressive increase" of space which
 * will block abuse attempts, but still provide enough space to all courses to
 * continue working progressively.
 * @package Chash\Command\Files
 */
class UpdateDirectoryMaxSizeCommand extends CommonChamiloDatabaseCommand
{
    /**
     *
     */
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('files:update_directory_max_size')
            ->setAliases(array('fudms'))
            ->setDescription('Increases the max disk space for all the courses reaching a certain threshold. Max space needs to be of at least 1MB for each course first.')
            ->addOption(
                'threshold',
                null,
                InputOption::VALUE_NONE,
                'Sets the threshold, in %, above which a course size should be automatically increased'
            )
            ->addOption(
                'add-size',
                null,
                InputOption::VALUE_NONE,
                'Number of MB to add to the max size of the course'
            )
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool|int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $add = $input->getOption('add-size'); //1 if the option was set
        if (empty($add)) {
            $add = 100;
        }
        $theshold = $input->getOption('threshold');
        if (empty($threshold)) {
            $threshold = 75;
        }
        $this->writeCommandHeader($output, 'Checking courses dir...');

        // Get database and path information
        $coursesPath = $this->getConfigurationHelper()->getSysPath();
        $this->getConfigurationHelper()->getConnection();
        $_configuration = $this->getConfigurationHelper()->getConfiguration();

        $courseTable = $_configuration['main_database'].'.course';
        $globalCourses = array();
        $sql = "SELECT c.id as cid, c.code as ccode, c.directory as cdir, c.disk_quota as cquota
                FROM $courseTable c";
        $res = mysql_query($sql);
        if ($res && mysql_num_rows($res) > 0) {
            while ($row = mysql_fetch_assoc($res)) {
                $globalCourses[$row['cdir']] = array('id' => $row['cid'], 'code' => $row['ccode'], 'quota' => $row['cquota']);
            }
        }

        $dirs = $this->getConfigurationHelper()->getDataFolders(1);
        if (count($dirs) > 0) {
            foreach ($dirs as $dir) {
                $file = $dir->getFileName();
                $res = exec('du -s '.$dir->getRealPath()); //results are in KB
                $res = preg_split('/\s/',$res);
                $size = round($res[0]/1024,1); // $size is stores in MB
                if (isset($globalCourses[$file]['code'])) {
                    $code = $globalCourses[$file]['code'];
                    $quota = round($globalCourses[$file]['quota']/(1024*1024), 0); //quota is originally in Bytes in DB. Store in MB
                    $rate = '-';
                    if ($quota > 0) {
                        $rate = round(($size/$quota)*100, 0);
                        if ($rate > $threshold) {
                            // Current disk usage goes beyond threshold. Increase allowed size by 100MB
                            $newQuota = ($quota + $add);
                            while ($newQuota < $size) {
                                $newQuota += $add;
                            }
                            $newQuota = $newQuota*1024*1024;
                            $sql = "UPDATE $courseTable SET disk_quota = $newQuota WHERE id = ".$globalCourses[$file]['id'];
                            $res = mysql_query($sql);
                            $output->writeln('Increased max size of '.$globalCourses[$file]['code'].'('.$globalCourses[$file]['id'].') to '.$newQuota);
                        }
                    }
                }
            }
        }
        $output->writeln('Done increasing disk space');
    }
}
