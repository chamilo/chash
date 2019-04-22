<?php

namespace Chash\Command\Database;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Chash\Command\Installation\CommonCommand;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Dotenv\Dotenv;

/**
 * Class CommonDatabaseCommand
 * @package Chash\Command\Database
 */
class CommonDatabaseCommand extends CommonCommand
{
    /**
     *
     */
    protected function configure()
    {
        $this
            ->addOption(
                'conf',
                null,
                InputOption::VALUE_OPTIONAL,
                'The configuration.php file path. Example /var/www/chamilo/config/configuration.php'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'For tests'
            );
    }

    /**
     * @inheritdoc
     */
    public function getConnection(InputInterface $input)
    {
        try {
            return $this->getHelper('db')->getConnection();
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    /**
     * @return \Doctrine\ORM\EntityManager
     */
    public function getEntityManager()
    {
        try {
            return $this->getHelper('em')->getEntityManager();
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $configurationFile = $input->getOption('conf');

        $this->getConfigurationHelper()->setDryRun($input->getOption('dry-run'));
        $configuration = $this->getConfigurationHelper()->readConfigurationFile($configurationFile);

        // Try 1.11.x
        if (empty($configuration)) {
            // Test out a few possibilities.
            $configurationFile = $this->getConfigurationHelper()->getConfigurationFilePath();
            $configuration = $this->getConfigurationHelper()->readConfigurationFile($configurationFile);
            if (empty($configuration)) {
                $showError = true;
                // Try 2.x
                $currentPath = getcwd();
                if (file_exists($currentPath.'/.env')) {
                    $showError = false;
                    $io->note('File: .env found '.$currentPath.'/.env');
                    $dotenv = new Dotenv();
                    $data = file_get_contents($currentPath.'/.env');
                    $values = $dotenv->parse($data);
                    $result = [];
                    foreach ($values as $key => $value) {
                        $result[] = [$key, $value];
                    }
                    $io->table(['Name', 'Value'], $result);

                    $configuration = [
                        'db_host' => $values['DATABASE_HOST'],
                        'main_database' => $values['DATABASE_NAME'],
                        'db_user' => $values['DATABASE_USER'],
                        'db_password' => $values['DATABASE_PASSWORD'],
                        'root_web' => '',
                        'system_version' => '2.x',
                    ];
                }

                if ($showError) {
                    $io->error('The configuration file was not found or Chamilo is not installed here');
                    $output->writeln(
                        '<comment>Try</comment> <info>prefix:command --conf=/var/www/chamilo/config/configuration.php</info>'
                    );

                    return false;
                }
            }
        }

        $this->setConfigurationArray($configuration);
        $this->getConfigurationHelper()->setConfiguration($configuration);
        $sysPath = $this->getConfigurationHelper()->getSysPathFromConfigurationFile($configurationFile);
        $this->getConfigurationHelper()->setSysPath($sysPath);
        $this->setRootSysDependingConfigurationPath($sysPath);

        if ($this->getConfigurationHelper()->isLegacy()) {
            $databaseSettings = [
                'driver' => 'pdo_mysql',
                'host' => $configuration['db_host'],
                'dbname' => $configuration['main_database'],
                'user' => $configuration['db_user'],
                'password' => $configuration['db_password']
            ];
        } else {
            $databaseSettings = [
                'driver' => 'pdo_mysql',
                'host' => $configuration['database_host'],
                'dbname' => $configuration['database_name'],
                'user' => $configuration['database_user'],
                'password' => $configuration['database_password']
            ];
        }

        // Setting doctrine connection
        $this->setDatabaseSettings($databaseSettings);
        $this->setDoctrineSettings($this->getHelperSet());
    }
}
