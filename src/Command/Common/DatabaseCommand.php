<?php

namespace Chash\Command\Common;

use Chash\Helpers\ConfigurationHelper;
use Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Dotenv\Dotenv;

/**
 * Class CommonDatabaseCommand.
 */
class DatabaseCommand extends CommonCommand
{
    public $configurationHelper;

    public function __construct(ConfigurationHelper $configurationHelper)
    {
        $this->configurationHelper = $configurationHelper;

        // you *must* call the parent constructor
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    public function getConnection()
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

    protected function configure(): void
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

    protected function execute(InputInterface $input, OutputInterface $output): int
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
                if (file_exists($currentPath.'/.env.local')) {
                    $showError = false;
                    $io->note('File: .env found '.$currentPath.'/.env.local');
                    $dotenv = new Dotenv();
                    $data = file_get_contents($currentPath.'/.env.local');
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

                    return 0;
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
                'password' => $configuration['db_password'],
            ];
        } else {
            $databaseSettings = [
                'driver' => 'pdo_mysql',
                'host' => $configuration['database_host'],
                'dbname' => $configuration['database_name'],
                'user' => $configuration['database_user'],
                'password' => $configuration['database_password'],
            ];
        }

        // Setting doctrine connection
        $this->setDatabaseSettings($databaseSettings);
        $this->setDoctrineSettings($this->getHelperSet());

        return 0;
    }
}
