<?php

namespace Chash\Command\Installation;

use Chash\Command\Common\CommonCommand;
use Chash\Helpers\ConfigurationHelper;
use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Dotenv\Dotenv;

/**
 * Class InstallCommand.
 */
class InstallCommand extends CommonCommand
{
    public $commandLine = true;
    public $oldConfigLocation = false;
    public $path;
    public $version;
    public $silent;
    public $download;
    public $tempFolder;
    public $linuxUser;
    public $linuxGroup;

    /**
     * @return int
     */
    public function installLegacy(InputInterface $input, OutputInterface $output)
    {
        $version = $this->version;
        $path = $this->path;
        $silent = $this->silent;
        $linuxUser = $this->linuxUser;
        $linuxGroup = $this->linuxGroup;
        $configurationPath = $this->getConfigurationHelper()->getConfigurationPath($path);

        if (empty($configurationPath)) {
            $output->writeln("<error>There was an error while loading the configuration path (looked for at $configurationPath). Are you sure this is a Chamilo path?</error>");
            $output->writeln('<comment>Try setting up a Chamilo path for example: </comment> <info>chamilo:install 1.11.x /var/www/chamilo</info>');
            $output->writeln('<comment>You can also *download* a Chamilo package adding the --download-package option:</comment>');
            $output->writeln('<info>chamilo:install 1.11.x /var/www/chamilo --download-package</info>');

            return 0;
        }

        if (!is_writable($configurationPath)) {
            $output->writeln('<error>Folder '.$configurationPath.' must be writable</error>');

            return 0;
        } else {
            $output->writeln('<comment>Configuration file will be saved here: </comment><info>'.$configurationPath.'configuration.php </info>');
        }

        $configurationDistExists = false;

        // Try the old one
        if (file_exists($this->getRootSys().'main/install/configuration.dist.php')) {
            $configurationDistExists = true;
        }

        if (false == $configurationDistExists) {
            $output->writeln('<error>configuration.dist.php file nof found</error> <comment>The file must exist in install/configuration.dist.php or app/config/parameter.yml');

            return 0;
        }

        if (file_exists($configurationPath.'configuration.php')) {
            if ($this->commandLine) {
                $output->writeln("<comment>There's a Chamilo portal here:</comment> <info>".$configurationPath.'</info>');
                $output->writeln("<comment>You should run <info>chash chash:chamilo_wipe $path </info><comment>if you want to start with a fresh install.</comment>");
                $output->writeln('<comment>You could also manually delete this file:</comment><info> sudo rm '.$configurationPath.'configuration.php</info>');
            } else {
                $output->writeln("<comment>There's a Chamilo portal here:</comment> <info>".$configurationPath.' </info>');
            }

            return 0;
        }

        if ($this->commandLine) {
            $this->askPortalSettings($input, $output);
            $this->askAdminSettings($input, $output);
            $this->askDatabaseSettings($input, $output);
        }

        $databaseSettings = $this->getDatabaseSettings();

        $connectionToHost = true;
        $connectionToHostConnect = true;
        if ($connectionToHostConnect) {
            if ($this->commandLine && false) {
                $eventManager = $connectionToHost->getSchemaManager();
                $databases = $eventManager->listDatabases();
                if (in_array($databaseSettings['dbname'], $databases)) {
                    if (false === $silent) {
                        /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
                        $helper = $this->getHelperSet()->get('question');
                        $question = new ConfirmationQuestion(
                            '<comment>The database <info>'.$databaseSettings['dbname'].'</info> exists and is going to be dropped!</comment> <question>Are you sure?</question>(y/N)',
                            true
                        );
                        if (!$helper->ask($input, $output, $question)) {
                            return 0;
                        }
                    }
                }
            }

            // When installing always drop the current database
            try {
                $output->writeln('Try connecting to drop and create the database: '.$databaseSettings['dbname']);
                /*
                $sm = $connectionToHost->getSchemaManager();
                $sm->dropAndCreateDatabase($databaseSettings['dbname']);
                $connectionToDatabase = $this->getUserAccessConnectionToDatabase();
                $connect = $connectionToDatabase->connect();*/
                $connect = true;
                if ($connect) {
                    /*$configurationWasSaved = $this->writeConfiguration($version, $path, $output);

                    if ($configurationWasSaved) {
                        $absPath = $this->getConfigurationHelper()->getConfigurationPath($path);
                        $output->writeln(
                            sprintf(
                                '<comment>Configuration file saved to %s. Proceeding with updating and cleaning stuff.</comment>',
                                $absPath
                            )
                        );
                    } else {
                        $output->writeln('<comment>Configuration file was not saved</comment>');

                        return 0;
                    }*/

                    // Installing database.
                    $result = $this->processInstallation($databaseSettings, $version, $output);
                    if ($result) {
                        // Read configuration file.
                        $configurationFile = $this->getConfigurationHelper()->getConfigurationFilePath($this->getRootSys());
                        $configuration = $this->getConfigurationHelper()->readConfigurationFile($configurationFile);
                        $this->setConfigurationArray($configuration);
                        $configPath = $this->getConfigurationPath();
                        // Only works with 10 >=
                        $installChamiloPath = str_replace('config', 'main/install', $configPath);
                        $customVersion = $installChamiloPath.$version;

                        $output->writeln('Checking custom *update.sql* file in dir: '.$customVersion);
                        if (is_dir($customVersion)) {
                            $file = $customVersion.'/update.sql';
                            if (is_file($file) && file_exists($file)) {
                                $output->writeln("File imported: $file");
                                $this->importSQLFile($file, $output);
                            }
                        } else {
                            $output->writeln('Nothing to update');
                        }

                        $manager = $this->getManager();
                        $connection = $manager->getConnection();

                        $this->setPortalSettingsInChamilo(
                            $output,
                            $connection
                        );

                        $this->setAdminSettingsInChamilo(
                            $output,
                            $connection
                        );

                        // Cleaning temp folders.
                        $command = $this->getApplication()->find('files:clean_temp_folder');
                        $arguments = [
                            'command' => 'files:clean_temp_folder',
                            '--conf' => $this->getConfigurationHelper()->getConfigurationFilePath($path),
                        ];

                        $input = new ArrayInput($arguments);
                        $input->setInteractive(false);
                        $command->run($input, $output);

                        // Generating temp folders.
                        $command = $this->getApplication()->find('files:generate_temp_folders');
                        $arguments = [
                            'command' => 'files:generate_temp_folders',
                            '--conf' => $this->getConfigurationHelper()->getConfigurationFilePath($path),
                        ];

                        $input = new ArrayInput($arguments);
                        $input->setInteractive(false);
                        $command->run($input, $output);

                        // Fixing permissions.
                        if (PHP_SAPI == 'cli') {
                            $command = $this->getApplication()->find('files:set_permissions_after_install');
                            $arguments = [
                                'command' => 'files:set_permissions_after_install',
                                '--conf' => $this->getConfigurationHelper()->getConfigurationFilePath($path),
                                '--linux-user' => $linuxUser,
                                '--linux-group' => $linuxGroup,
                                //'--dry-run' => $dryRun
                            ];

                            $input = new ArrayInput($arguments);
                            $input->setInteractive(false);
                            $command->run($input, $output);
                        }
                        // Generating config files (auth, profile, etc)
                        //$this->generateConfFiles($output);
                        $output->writeln('<comment>Chamilo was successfully installed here: '.$this->getRootSys().' </comment>');

                        return 1;
                    } else {
                        $output->writeln('<comment>Error during installation.</comment>');

                        return 0;
                    }
                } else {
                    $output->writeln("<comment>Can't create database '".$databaseSettings['dbname']."' </comment>");

                    return 0;
                }
            } catch (\Exception $e) {
                // Delete configuration.php because installation failed
                unlink($this->getRootSys().'app/config/configuration.php');

                $output->writeln(
                    sprintf(
                        '<error>Could not create database for connection named <comment>%s</comment></error>',
                        $databaseSettings['dbname']
                    )
                );
                $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));

                return 0;
            }
        } else {
            $output->writeln(
                sprintf(
                    '<error>Could not connect to database %s. Please check the database connection parameters.</error>',
                    $databaseSettings['dbname']
                )
            );

            return 0;
        }
    }

    /**
     * Install Chamilo.
     *
     * @return false|int|null
     */
    public function install(InputInterface $input, OutputInterface $output)
    {
        // Install chamilo in /var/www/html/chamilo-test path

        // Master
        // sudo php /var/www/html/chash/chash.php chash:chamilo_install --download-package --sitename=Chamilo --institution=Chami --institution_url=http://localhost/chamilo-test --encrypt_method=sha1 --permissions_for_new_directories=0777 --permissions_for_new_files=0777 --firstname=John --lastname=Doe --username=admin --password=admin --email=admin@example.com --language=english --phone=666 --driver=pdo_mysql --host=localhost --port=3306 --dbname=chamilo_test --dbuser=root --dbpassword=root master /var/www/html/chamilo-test

        // 1.11.x
        // sudo php /var/www/html/chash/chash.php chash:chamilo_install --download-package --sitename=Chamilo --institution=Chami --institution_url=http://localhost/chamilo-test --encrypt_method=sha1 --permissions_for_new_directories=0777 --permissions_for_new_files=0777 --firstname=John --lastname=Doe --username=admin --password=admin --email=admin@example.com --language=english --phone=666 --driver=pdo_mysql --host=localhost --port=3306 --dbname=chamilo_test --dbuser=root --dbpassword=root  --site_url=http://localhost/chamilo-test 1.11.x /var/www/html/chamilo-test

        // 1.10.x
        // sudo php /var/www/html/chash/chash.php chash:chamilo_install --download-package --sitename=Chamilo --institution=Chami --institution_url=http://localhost/chamilo-test --encrypt_method=sha1 --permissions_for_new_directories=0777 --permissions_for_new_files=0777 --firstname=John --lastname=Doe --username=admin --password=admin --email=admin@example.com --language=english --phone=666 --driver=pdo_mysql --host=localhost --port=3306 --dbname=chamilo_test --dbuser=root --dbpassword=root  --site_url=http://localhost/chamilo-test 1.10.x /var/www/html/chamilo-test

        // 1.9.0
        // sudo rm /var/www/html/chamilo-test/main/inc/conf/configuration.php

        /*
            sudo rm -R /var/www/html/chamilo-test/
            sudo php /var/www/html/chash/chash.php chash:chamilo_install --download-package --sitename=Chamilo --institution=Chami --institution_url=http://localhost/chamilo-test --encrypt_method=sha1 --permissions_for_new_directories=0777 --permissions_for_new_files=0777 --firstname=John --lastname=Doe --username=admin --password=admin --email=admin@example.com --language=english --phone=666 --driver=pdo_mysql --host=localhost --port=3306 --dbname=chamilo_test --dbuser=root --dbpassword=root  --site_url=http://localhost/chamilo-test 1.9.0 /var/www/html/chamilo-test
            cd /var/www/html/chamilo-test/
        */
        $this->askDatabaseSettings($input, $output);
        $this->askPortalSettings($input, $output);
        $this->askAdminSettings($input, $output);

        $databaseSettings = $this->databaseSettings;
        $silent = $this->silent;

        if (empty($this->databaseSettings)) {
            $output->writeln('<comment>Cannot get database settings. </comment>');

            return false;
        }

        if ($this->commandLine) {
            $connectionToHost = $this->getUserAccessConnectionToHost();
            $connectionToHostConnect = $connectionToHost->connect();

            if ($connectionToHostConnect) {
                $output->writeln(
                    sprintf(
                        '<comment>Connection to database %s established. </comment>',
                        $databaseSettings['dbname']
                    )
                );
            } else {
                $output->writeln(
                    sprintf(
                        '<error>Could not connect to database %s. Please check the database connection parameters.</error>',
                        $databaseSettings['dbname']
                    )
                );

                return 0;
            }

            $eventManager = $connectionToHost->getSchemaManager();
            $databases = $eventManager->listDatabases();
            if (in_array($databaseSettings['dbname'], $databases)) {
                if (false == $silent) {
                    $helper = $this->getHelperSet()->get('question');
                    $question = new ConfirmationQuestion(
                        '<comment>The database <info>'.$databaseSettings['dbname'].'</info> exists and is going to be dropped!</comment> <question>Are you sure?</question>(y/N)',
                        false
                    );
                    if (!$helper->ask($input, $output, $question)) {
                        return 0;
                    }
                }
            }

            $version = $this->version;

            // Installing database.
            $result = $this->processInstallation($this->databaseSettings, $version, $output);
            if ($result) {
                $path = $this->path;
                $linuxUser = $this->linuxUser;
                $linuxGroup = $this->linuxGroup;

                $configurationWasSaved = $this->writeConfiguration($version, $path, $output);
                if ($configurationWasSaved) {
                    $this->setDoctrineSettings($this->getHelperSet());
                    $this->setPortalSettingsInChamilo(
                        $output,
                        $this->getHelper('db')->getConnection()
                    );
                }
            }
        }
    }

    /**
     * Ask for DB settings.
     */
    public function askDatabaseSettings(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelperSet()->get('question');
        $filledParams = $this->getParamsFromOptions($input, $this->getDatabaseSettingsParams());
        $params = $this->getDatabaseSettingsParams();
        $total = count($params);
        $output->writeln(
            '<comment>Database settings: ('.$total.')</comment>'
        );
        $databaseSettings = [];
        $counter = 1;
        foreach ($params as $key => $value) {
            if (!isset($filledParams[$key])) {
                if (!$input->isInteractive() &&
                    (in_array($key, ['dbpassword', 'port', 'host', 'driver']))
                ) {
                    // db password may be empty, so if not provided and the
                    // --no-interaction mode was configured, forget about it
                    switch ($key) {
                        case 'dbpassword':
                            $databaseSettings[$key] = '';
                            $output->writeln(
                                "($counter/$total) <comment>Option: $key was not provided. Using default value null (empty password)</comment>"
                            );

                            break;
                        case 'host':
                            $databaseSettings[$key] = 'localhost';
                            $output->writeln(
                                "($counter/$total) <comment>Option: $key was not provided. Using default value ".$databaseSettings[$key].'</comment>'
                            );

                            break;
                        case 'port':
                            $databaseSettings[$key] = '3306';
                            $output->writeln(
                                "($counter/$total) <comment>Option: $key was not provided. Using default value ".$databaseSettings[$key].'</comment>'
                            );

                            break;
                        case 'driver':
                            $databaseSettings[$key] = 'pdo_mysql';
                            $output->writeln(
                                "($counter/$total) <comment>Option: $key was not provided. Using default value ".$databaseSettings[$key].'</comment>'
                            );

                            break;
                    }
                    ++$counter;
                } else {
                    $question = new Question(
                        "($counter/$total) Please enter the value of the $key (".$value['attributes']['data'].'): '
                    );

                    $data = $helper->ask($input, $output, $question);
                    ++$counter;
                    $databaseSettings[$key] = $data;
                }
            } else {
                $output->writeln(
                    "($counter/$total) <comment>Option: $key = '".$filledParams[$key]."' was added as an option. </comment>"
                );
                ++$counter;
                $databaseSettings[$key] = $filledParams[$key];
            }
        }
        $this->setDatabaseSettings($databaseSettings);
    }

    /**
     * Asks for admin settings.
     */
    public function askAdminSettings(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelperSet()->get('question');

        // Ask for admin settings
        $filledParams = $this->getParamsFromOptions(
            $input,
            $this->getAdminSettingsParams()
        );

        $params = $this->getAdminSettingsParams();
        $total = count($params);
        $output->writeln(
            '<comment>Admin settings: ('.$total.')</comment>'
        );
        $adminSettings = [];
        $counter = 1;

        foreach ($params as $key => $value) {
            if (!isset($filledParams[$key])) {
                $question = new Question("($counter/$total) Please enter the value of the $key (".$value['attributes']['data'].'): ');
                $data = $helper->ask($input, $output, $question);
                ++$counter;
                $adminSettings[$key] = $data;
            } else {
                $output->writeln(
                    "($counter/$total) <comment>Option: $key = '".$filledParams[$key]."' was added as an option. </comment>"
                );
                ++$counter;
                $adminSettings[$key] = $filledParams[$key];
            }
        }

        $this->setAdminSettings($adminSettings);
    }

    /**
     * Ask for portal settings.
     */
    public function askPortalSettings(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelperSet()->get('question');

        // Ask for portal settings.
        $filledParams = $this->getParamsFromOptions($input, $this->getPortalSettingsParams());

        $params = $this->getPortalSettingsParams();
        $total = count($params);
        $portalSettings = [];

        $output->writeln('<comment>Portal settings ('.$total.') </comment>');

        $counter = 1;
        foreach ($params as $key => $value) {
            // If not in array ASK!
            if (!isset($filledParams[$key])) {
                $question = new Question("($counter/$total) Please enter the value of the $key (".$value['attributes']['data'].'): ');
                $data = $helper->ask($input, $output, $question);
                ++$counter;
                $portalSettings[$key] = $data;
            } else {
                $output->writeln("($counter/$total) <comment>Option: $key = '".$filledParams[$key]."' was added as an option. </comment>");

                $portalSettings[$key] = $filledParams[$key];
                ++$counter;
            }
        }

        $this->setPortalSettings($portalSettings);
    }

    /**
     * Setting common parameters.
     */
    public function settingParameters(InputInterface $input)
    {
        if (PHP_SAPI != 'cli') {
            $this->commandLine = false;
        }

        // Arguments
        $this->path = $input->getArgument('path');
        $this->version = $input->getArgument('version');
        $this->silent = true === $input->getOption('silent');
        $this->download = $input->getOption('download-package');
        $this->tempFolder = $input->getOption('temp-folder');
        $this->linuxUser = $input->getOption('linux-user');
        $this->linuxGroup = $input->getOption('linux-group');

        // Getting the new config folder.
        $configurationPath = $this->getConfigurationHelper()->getNewConfigurationPath($this->path);

        // @todo move this in the helper
        if (false === $configurationPath) {
            // Seems an old installation!
            $configurationPath = $this->getConfigurationHelper()->getConfigurationPath($this->path);

            if (false === strpos($configurationPath, 'app/config')) {
                // Version 1.9.x
                $this->setRootSys(
                    realpath($configurationPath.'/../../../').'/'
                );
                $this->oldConfigLocation = true;
            } else {
                // Version 1.10.x
                // Legacy but with new location app/config
                $this->setRootSys(realpath($configurationPath.'/../../').'/');
                $this->oldConfigLocation = true;
            }
        } else {
            // Chamilo v2/v1.x installation.
            /*$this->setRootSys(realpath($configurationPath.'/../').'/');
            $this->oldConfigLocation = false;*/
            $this->setRootSys(realpath($configurationPath).'/');
            $this->oldConfigLocation = true;
        }

        $this->getConfigurationHelper()->setIsLegacy($this->oldConfigLocation);
        $this->setConfigurationPath($configurationPath);
    }

    /**
     * Get database version to install for a requested version.
     *
     * @param string $version
     *
     * @return string
     */
    public function getVersionToInstall($version)
    {
        $newVersion = $this->getLatestVersion();
        switch ($version) {
            case '1.8.7':
                $newVersion = '1.8.7';

                break;
            case '1.8.8.0':
            case '1.8.8.6':
            case '1.8.8.8':
                $newVersion = '1.8.0';

                break;
            case '1.9.0':
            case '1.9.1':
            case '1.9.2':
            case '1.9.4':
            case '1.9.6':
            case '1.9.8':
            case '1.9.10':
            case '1.9.10.2':
            case '1.9.x':
                $newVersion = '1.9.0';

                break;
            case '1.10':
            case '1.10.0':
            case '1.10.x':
                $newVersion = '1.10.0';

                break;
            case '1.11.x':
                $newVersion = '1.11.0';

                break;
            case '2':
            case '2.0':
            case 'master':
                $newVersion = '2.0';

                break;
        }

        return $newVersion;
    }

    /**
     * Installation command.
     *
     * @param array  $databaseSettings
     * @param string $version
     * @param $output
     *
     * @return bool
     */
    public function processInstallation($databaseSettings, $version, OutputInterface $output)
    {
        $sqlFolder = $this->getInstallationPath($version);
        $databaseMap = $this->getDatabaseMap();
        // Fixing the version
        if (!isset($databaseMap[$version])) {
            $version = $this->getVersionToInstall($version);
        }

        if (isset($databaseMap[$version])) {
            $dbInfo = $databaseMap[$version];
            $output->writeln("<comment>Starting creation of database version </comment><info>$version... </info>");
            $sections = $dbInfo['section'];
            $sectionsCount = 0;
            foreach ($sections as $sectionData) {
                if (is_array($sectionData)) {
                    foreach ($sectionData as $dbInfo) {
                        $databaseName = $dbInfo['name'];
                        $dbList = $dbInfo['sql'];
                        if (!empty($dbList)) {
                            $output->writeln(
                                "<comment>Creating database</comment> <info>$databaseName ... </info>"
                            );

                            if (empty($dbList)) {
                                $output->writeln(
                                    '<error>No files to load.</error>'
                                );

                                continue;
                            } else {
                                // Fixing db list
                                foreach ($dbList as &$db) {
                                    $db = $sqlFolder.$db;
                                }

                                $command = $this->getApplication()->find('dbal:import');
                                // Getting extra information about the installation.
                                $output->writeln("<comment>Calling file: $dbList</comment>");
                                // Importing sql files.
                                $arguments = [
                                    'command' => 'dbal:import',
                                    'file' => $dbList,
                                ];
                                $input = new ArrayInput($arguments);
                                $command->run($input, $output);

                                // Getting extra information about the installation.
                                $output->writeln(
                                    "<comment>Database </comment><info>$databaseName </info><comment>setup process terminated successfully!</comment>"
                                );
                            }
                            ++$sectionsCount;
                        }
                    }
                }
            }

            // Run
            if (isset($sections) && isset($sections['course'])) {
                //@todo fix this
                foreach ($sections['course'] as $courseInfo) {
                    $databaseName = $courseInfo['name'];
                    $output->writeln("Inserting course database in Chamilo: <info>$databaseName</info>");
                    $this->createCourse($this->getHelper('db')->getConnection(), $databaseName);
                    ++$sectionsCount;
                }
            }

            // Special migration for chamilo using install global.inc.php
            if (isset($sections) && isset($sections['migrations'])) {
                $sectionsCount = 1;
                $legacyFiles = [
                    '/vendor/autoload.php',
                    '/public/main/install/install.lib.php',
                    '/public/legacy.php',
                ];

                foreach ($legacyFiles as $file) {
                    $file = $this->getRootSys().$file;
                    if (file_exists($file)) {
                        require_once $file;
                    } else {
                        $output->writeln(
                            "<error>File is missing: $file. Run composer update. In ".$this->getRootSys().'</error>'
                        );
                        exit;
                    }
                }

                $portalSettings = $this->getPortalSettings();
                $adminSettings = $this->getAdminSettings();
                $newInstallationPath = $this->getRootSys();

                if ('master' === $version) {
                    $params = [
                        '{{DATABASE_HOST}}' => $databaseSettings['host'],
                        '{{DATABASE_PORT}}' => $databaseSettings['port'],
                        '{{DATABASE_NAME}}' => $databaseSettings['dbname'],
                        '{{DATABASE_USER}}' => $databaseSettings['user'],
                        '{{DATABASE_PASSWORD}}' => $databaseSettings['password'],
                        '{{APP_INSTALLED}}' => 1,
                        '{{APP_ENCRYPT_METHOD}}' => $portalSettings['encrypt_method'],
                    ];

                    $envFile = $this->getRootSys().'.env.local';
                    $distFile = $this->getRootSys().'.env';
                    \updateEnvFile($distFile, $envFile, $params);

                    if (file_exists($envFile)) {
                        $output->writeln("<comment>Env file created: $envFile</comment>");
                    } else {
                        $output->writeln("<error>File not created: $envFile</error>");
                        exit;
                    }

                    (new Dotenv())->load($envFile);

                    $output->writeln("<comment>File loaded: $envFile</comment>");

                    $kernel = new \Chamilo\Kernel('dev', true);
                    $kernel->boot();

                    $output->writeln('<comment>Booting kernel</comment>');

                    $container = $kernel->getContainer();
                    $doctrine = $container->get('doctrine');

                    $output->writeln('<comment>Creating Application object:</comment>');
                    $application = new Application($kernel);

                    try {
                        // Drop database if exists
                        $command = $application->find('doctrine:database:drop');
                        $input = new ArrayInput([], $command->getDefinition());
                        $input->setOption('force', true);
                        $input->setOption('if-exists', true);
                        $command->execute($input, new ConsoleOutput());
                    } catch (\Exception $e) {
                        error_log($e->getMessage());
                    }

                    // Create database
                    $input = new ArrayInput([]);
                    $command = $application->find('doctrine:database:create');
                    $command->run($input, new ConsoleOutput());

                    // Create schema
                    $command = $application->find('doctrine:schema:create');
                    $result = $command->run($input, new ConsoleOutput());

                    // No errors
                    if (0 == $result) {
                        $kernel->boot();
                        $container = $kernel->getContainer();
                        $manager = $doctrine->getManager();

                        // Boot kernel and get the doctrine from Symfony container
                        $output->writeln('<comment>Database created</comment>');
                        $this->setManager($manager);

                        $output->writeln("<comment>Calling 'finishInstallationWithContainer()'</comment>");
                        \finishInstallationWithContainer(
                            $container,
                            $newInstallationPath,
                            $portalSettings['encrypt_method'],
                            $adminSettings['password'],
                            $adminSettings['lastname'],
                            $adminSettings['firstname'],
                            $adminSettings['username'],
                            $adminSettings['email'],
                            $adminSettings['phone'],
                            $adminSettings['language'],
                            $portalSettings['institution'],
                            $portalSettings['institution_url'],
                            $portalSettings['sitename'],
                            false, //$allowSelfReg,
                            false //$allowSelfRegProf
                        );
                    } else {
                        $output->writeln('<error>Cannot create database</error>');
                        exit;
                    }
                } else {
                    $chashPath = __DIR__.'/../../../chash/';
                    $database = new \Database();
                    $database::$utcDateTimeClass = 'Chash\DoctrineExtensions\DBAL\Types\UTCDateTimeType';
                    $output->writeln('<comment>Connect to database</comment>');
                    $database->connect($databaseSettings, $chashPath, $newInstallationPath);

                    /** @var EntityManager $manager */
                    $manager = $database->getManager();

                    // Create database schema
                    $output->writeln('<comment>Creating schema</comment>');
                    $tool = new \Doctrine\ORM\Tools\SchemaTool($manager);
                    $tool->createSchema($metadataList);
                    $output->writeln("<comment>Calling 'finishInstallation()'</comment>");

                    \finishInstallation(
                        $manager,
                        $newInstallationPath,
                        $portalSettings['encrypt_method'],
                        $adminSettings['password'],
                        $adminSettings['lastname'],
                        $adminSettings['firstname'],
                        $adminSettings['username'],
                        $adminSettings['email'],
                        $adminSettings['phone'],
                        $adminSettings['language'],
                        $portalSettings['institution'],
                        $portalSettings['institution_url'],
                        $portalSettings['sitename'],
                        false, //$allowSelfReg,
                        false //$allowSelfRegProf
                    );
                }
                $output->writeln('<comment>Remember to run composer install</comment>');
            }

            if (0 == $sectionsCount) {
                $output->writeln('<comment>No database section found for creation</comment>');
            }

            $output->writeln('<comment>Check your installation status with </comment><info>chamilo:status</info>');

            return true;
        } else {
            $output->writeln("<comment>Unknown version: </comment> <info>$version</info>");
        }

        return false;
    }

    /**
     * In step 3. Tests establishing connection to the database server.
     * If it's a single database environment the function checks if the database exist.
     * If the database doesn't exist we check the creation permissions.
     *
     * @return int 1 when there is no problem;
     *             0 when a new database is impossible to be created,
     *             then the single/multiple database configuration is impossible too
     *             -1 when there is no connection established
     */
    public function testDatabaseConnection()
    {
        $conn = $this->testUserAccessConnection();

        return $conn->connect();
    }

    /**
     * @return \Doctrine\DBAL\Connection
     */
    public function getUserAccessConnectionToHost()
    {
        $config = new \Doctrine\DBAL\Configuration();
        $settings = $this->getDatabaseSettings();
        $settings['dbname'] = null;

        return \Doctrine\DBAL\DriverManager::getConnection(
            $settings,
            $config
        );
    }

    /**
     * @return \Doctrine\DBAL\Connection
     */
    public function getUserAccessConnectionToDatabase()
    {
        $config = new \Doctrine\DBAL\Configuration();
        $settings = $this->getDatabaseSettings();

        return \Doctrine\DBAL\DriverManager::getConnection(
            $settings,
            $config
        );
    }

    /**
     * Creates a course (only an insert in the DB).
     *
     * @param \Doctrine\DBAL\Connection $connection
     * @param string                    $databaseName
     */
    public function createCourse($connection, $databaseName)
    {
        $params = [
            'code' => $databaseName,
            'db_name' => $databaseName,
            'course_language' => 'english',
            'title' => $databaseName,
            'visual_code' => $databaseName,
        ];
        $connection->insert('course', $params);
    }

    /**
     * Configure command.
     */
    protected function configure(): void
    {
        $this
            ->setName('chash:chamilo_install')
            ->setDescription('Execute a Chamilo installation to a specified version.')
            ->addArgument('version', InputArgument::REQUIRED, 'The version to migrate to.', null)
            ->addArgument('path', InputArgument::OPTIONAL, 'The path to the chamilo folder')
            ->addOption('download-package', null, InputOption::VALUE_NONE, 'Downloads the chamilo package')
            ->addOption('only-download-package', null, InputOption::VALUE_NONE, 'Only downloads the package')
            ->addOption('temp-folder', null, InputOption::VALUE_OPTIONAL, 'The temp folder.', '/tmp')
            ->addOption('linux-user', null, InputOption::VALUE_OPTIONAL, 'user', 'www-data')
            ->addOption('linux-group', null, InputOption::VALUE_OPTIONAL, 'group', 'www-data')
            ->addOption('silent', null, InputOption::VALUE_NONE, 'Execute the migration with out asking questions.')
            ;

        $params = $this->getPortalSettingsParams();

        foreach ($params as $key => $value) {
            $this->addOption($key, null, InputOption::VALUE_OPTIONAL);
        }

        $params = $this->getAdminSettingsParams();
        foreach ($params as $key => $value) {
            $this->addOption($key, null, InputOption::VALUE_OPTIONAL);
        }

        $params = $this->getDatabaseSettingsParams();
        foreach ($params as $key => $value) {
            $this->addOption($key, null, InputOption::VALUE_OPTIONAL);
        }
    }

    /**
     * Executes a command via CLI.
     *
     * @return false|int|null
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Setting configuration helper.
        $this->getApplication()->getHelperSet()->set(
            new ConfigurationHelper(),
            'configuration'
        );

        $this->settingParameters($input);
        $output->writeln('Root sys value: '.$this->rootSys);

        $version = $this->version;
        $download = $this->download;
        $tempFolder = $this->tempFolder;
        $path = $this->path;

        // @todo fix process in order to install minor versions: 1.9.6
        $versionList = $this->getVersionNumberList();

        if (!in_array($version, $versionList)) {
            $output->writeln("<comment>Sorry you can't install version: '$version' of Chamilo :(</comment>");
            $output->writeln('<comment>Supported versions:</comment> <info>'.implode(', ', $this->getVersionNumberList()));

            return 0;
        }

        if ($download) {
            $chamiloLocationPath = $this->getPackage($output, $version, null, $tempFolder);
            if (empty($chamiloLocationPath)) {
                return 0;
            }

            $result = $this->copyPackageIntoSystem($output, $chamiloLocationPath, $path);
            if (0 == $result) {
                return 0;
            }

            $this->settingParameters($input);
            if ($input->getOption('only-download-package')) {
                return 0;
            }
        }

        $title = 'Chamilo installation process.';
        if ($this->commandLine) {
            $title = 'Welcome to the Chamilo installation process.';
        }

        $this->writeCommandHeader($output, $title);

        $versionInfo = $this->availableVersions()[$version];
        if (isset($versionInfo['parent'])) {
            $parent = $versionInfo['parent'];
            if (in_array($parent, ['1.9.0', '1.10.0', '1.11.0'])) {
                $isLegacy = true;
            } else {
                $isLegacy = false;
            }
        } else {
            $output->writeln("<comment>Chamilo $version doesnt have a parent</comment>");

            return false;
        }

        if ($isLegacy) {
            $this->installLegacy($input, $output);
        } else {
            $this->install($input, $output);
        }

        return 0;
    }

    /**
     * @param $file
     * @param $output
     *
     * @throws \Exception
     */
    private function importSQLFile(string $file, OutputInterface $output)
    {
        $command = $this->getApplication()->find('dbal:import');

        // Importing sql files.
        $arguments = [
            'command' => 'dbal:import',
            'file' => $file,
        ];
        $input = new ArrayInput($arguments);
        $command->run($input, $output);

        // Getting extra information about the installation.
        $output->writeln("<comment>File loaded </comment><info>$file</info>");
    }
}
