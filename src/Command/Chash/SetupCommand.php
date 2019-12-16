<?php

namespace Chash\Command\Chash;

use Doctrine\Migrations\Tools\Console\Command\AbstractCommand;
use Symfony\Component\Console;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Dumper;

/**
 * Class SetupCommand.
 */
class SetupCommand extends AbstractCommand
{
    public $migrationFile;

    /**
     * Gets the migration file path.
     *
     * @return string
     */
    public function getMigrationFile()
    {
        return $this->migrationFile;
    }

    protected function configure(): void
    {
        $this
            ->setName('chash:setup')
            ->addArgument('version', InputArgument::REQUIRED, 'The version to migrate to', null)
            ->addArgument('chamilo_root', InputArgument::REQUIRED, 'Chamilo root', null)
            ->setDescription('Setups the migration.yml')
            ->addOption('temp-folder', null, InputOption::VALUE_OPTIONAL, 'The temp folder.', '/tmp');
    }

    /**
     * Executes a command via CLI.
     *
     * @return int|void|null
     */
    protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output)
    {
        $tempFolder = $input->getOption('temp-folder');

        $fs = new Filesystem();

        $version = $input->getArgument('version');
        $chamiloRoot = $input->getArgument('chamilo_root');

        if ('111' == $version) {
            $file = $chamiloRoot.'app/config/migrations.yml';

            require_once $chamiloRoot.'app/Migrations/AbstractMigrationChamilo.php';

            $this->migrationFile = $file;

            return 1;
        }

        if ('110' == $version) {
            $file = $chamiloRoot.'app/config/migrations110.yml';

            $this->migrationFile = $file;

            return 1;
        }

        require_once $chamiloRoot.'app/Migrations/AbstractMigrationChamilo.php';
        $migrationsFolder = $tempFolder.'/Migrations/';

        if (!$fs->exists($migrationsFolder)) {
            $fs->mkdir($migrationsFolder);
        }

        $migrations = [
            'name' => 'Chamilo Migrations',
            'migrations_namespace' => 'Application\Migrations\Schema\V111',
            'table_name' => 'version',
            'migrations_directory' => $migrationsFolder,
        ];

        $dumper = new Dumper();
        $yaml = $dumper->dump($migrations, 1);
        $file = $migrationsFolder.'migrations.yml';
        file_put_contents($file, $yaml);

        $migrationPathSource = __DIR__.'/../../../Chash/Migrations/';

        $fs->mirror($migrationPathSource, $migrationsFolder);

        // migrations_directory
        $output->writeln("<comment>Chash migrations.yml saved: $file</comment>");
        $this->migrationFile = $file;
    }
}
