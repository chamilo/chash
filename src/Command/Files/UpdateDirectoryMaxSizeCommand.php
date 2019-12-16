<?php

namespace Chash\Command\Files;

use Chash\Command\Common\DatabaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class UpdateDirectoryMaxSizeCommand
 * Increase the maximum space allowed on disk progressively. This command is
 * used called once every night, to make a "progressive increase" of space which
 * will block abuse attempts, but still provide enough space to all courses to
 * continue working progressively.
 */
class UpdateDirectoryMaxSizeCommand extends DatabaseCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('files:update_directory_max_size')
            ->setAliases(['fudms'])
            ->setDescription('Increases the max disk space for all the courses reaching a certain threshold.')
            ->addArgument(
                'threshold',
                InputArgument::REQUIRED,
                'Sets the threshold, in %, above which a course size should be automatically increased'
            )
            ->addArgument(
                'size',
                InputArgument::REQUIRED,
                'Number of MB to add to the max size of the course'
            )
        ;
    }

    /**
     * @return int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);
        $conn = $this->getConnection($input);

        $add = $input->getArgument('size'); //1 if the option was set
        if (empty($add)) {
            $add = 100;
        }

        if (1 == $add) {
            $this->writeCommandHeader($output, 'Max space needs to be of at least 1MB for each course first');

            return 0;
        }

        if ($conn instanceof \Doctrine\DBAL\Connection) {
            $threshold = $input->getArgument('threshold');
            if (empty($threshold)) {
                $threshold = 75;
            }
            $this->writeCommandHeader($output, 'Using threshold: '.$threshold);
            $this->writeCommandHeader($output, 'Checking courses dir...');

            // Get database and path information
            $coursesPath = $this->getConfigurationHelper()->getSysPath();
            $connection = $this->getConnection($input);
            $_configuration = $this->getConfigurationHelper()->getConfiguration();

            $courseTable = $_configuration['main_database'].'.course';
            $globalCourses = [];
            $sql = "SELECT c.id as cid, c.code as ccode, c.directory as cdir, c.disk_quota as cquota
                    FROM $courseTable c";

            try {
                $stmt = $conn->prepare($sql);
                $stmt->execute();
            } catch (\PDOException $e) {
                $output->write('SQL error!'.PHP_EOL);

                throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
            }
            if ($stmt->rowCount() > 0) {
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $globalCourses[$row['cdir']] = [
                        'id' => $row['cid'],
                        'code' => $row['ccode'],
                        'quota' => $row['cquota'],
                    ];
                }
            }

            $dirs = $this->getConfigurationHelper()->getDataFolders();
            if (count($dirs) > 0) {
                foreach ($dirs as $dir) {
                    $file = $dir->getFileName();
                    $res = exec('du -s '.$dir->getRealPath()); // results are returned in KB (under Linux)
                    $res = preg_split('/\s/', $res);
                    $size = round($res[0] / 1024, 1); // $size is stored in MB
                    if (isset($globalCourses[$file]['code'])) {
                        $code = $globalCourses[$file]['code'];
                        $quota = round(
                            $globalCourses[$file]['quota'] / (1024 * 1024),
                            0
                        ); //quota is originally in Bytes in DB. Store in MB
                        $rate = '-';
                        if ($quota > 0) {
                            $newAllowedSize = $quota;
                            $rate = round(
                                ($size / $newAllowedSize) * 100,
                                0
                            ); //rate is a percentage of disk use vs allowed quota, in MB
                            $increase = false;
                            while ($rate > $threshold) { // Typically 80 > 75 -> increase quota
                                //$output->writeln('...Rate '.$rate.' is larger than '.$threshold.', so increase allowed size');
                                // Current disk usage goes beyond threshold. Increase allowed size by 100MB
                                $newAllowedSize += $add;
                                //$output->writeln('....New allowed size is '.$newAllowedSize);
                                $rate = round(($size / $newAllowedSize) * 100, 0);
                                //$output->writeln('...Rate is now '.$rate);
                                $increase = true;
                            }
                            $newAllowedSize = $newAllowedSize * 1024 * 1024;
                            //$output->writeln('Allowed size is '.$newAllowedSize.' Bytes, or '.round($newAllowedSize/(1024*1024)));
                            $sql = "UPDATE $courseTable SET disk_quota = $newAllowedSize WHERE id = ".$globalCourses[$file]['id'];

                            try {
                                $stmt2 = $conn->prepare($sql);
                                $stmt2->execute();
                            } catch (\PDOException $e) {
                                $output->write('SQL error!'.PHP_EOL);

                                throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
                            }
                            if ($increase) {
                                $output->writeln('Increased max size of '.$globalCourses[$file]['code'].'('.$globalCourses[$file]['id'].') to '.$newAllowedSize);
                            }
                        } else {
                            //Quota is 0 (unlimited?)
                        }
                    }
                }
            }
            $output->writeln('Done increasing disk space');
        } else {
            $output->writeln('The connection does not seem to be a valid PDO connection');
        }

        return 0;
    }
}
