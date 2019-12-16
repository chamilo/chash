<?php

namespace Chash\Command\Info;

use Chash\Command\Common\CommonCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Class GetInstancesCommand.
 */
class GetInstancesCommand extends CommonCommand
{
    /**
     * @param string $configurationFile
     *
     * @return array
     */
    public function getPortalInfoFromConfiguration($configurationFile)
    {
        $fs = new Filesystem();

        if ($fs->exists($configurationFile)) {
            $lines = file($configurationFile, FILE_IGNORE_NEW_LINES);
            $version = '';
            $url = '';
            $packager = '';
            foreach ($lines as $line) {
                if (false !== strpos($line, 'system_version')) {
                    $replace = [
                        "\$_configuration['system_version']",
                        '=',
                        ';',
                        "'",
                    ];
                    $version = str_replace($replace, '', $line);
                }
                if (false !== strpos($line, 'root_web')) {
                    $replace = [
                        "\$_configuration['root_web']",
                        '=',
                        ';',
                        "'",
                    ];
                    $url = str_replace($replace, '', $line);
                }
                if (false !== strpos($line, 'packager')) {
                    $replace = [
                        "\$_configuration['packager']",
                        '=',
                        ';',
                        "'",
                        '//',
                    ];
                    $packager = str_replace($replace, '', $line);
                }
            }
            $portal = [$url, $version, $packager, $configurationFile];
            $portal = array_map('trim', $portal);

            return $portal;
        }

        return [];
    }

    protected function configure(): void
    {
        $this
            ->setName('info:get_instances')
            ->setDescription('Get chamilo instances info')
            ->addArgument(
                'path',
                InputArgument::REQUIRED,
                '/var/www/'
            )
            ->addArgument(
                'folder',
                InputArgument::OPTIONAL,
                'www'
            )
        ;
    }

    /**
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $path = $input->getArgument('path');
        $folderInsidePath = $input->getArgument('folder');

        $output->writeln("Checking chamilo portals here: $path");
        $fs = new Filesystem();
        $finder = new Finder();
        $dirs = $finder->directories()->in($path)->depth('== 0');
        $portals = [];
        if (!empty($folderInsidePath)) {
            $output->writeln("Checking chamilo portals inside subfolder: $folderInsidePath");
            $folderInsidePath = '/'.$folderInsidePath;
        }

        /** @var SplFileInfo $dir */
        foreach ($dirs as $dir) {
            $appPath = $dir->getRealPath().$folderInsidePath;
            $configurationFile = $appPath.'/app/config/configuration.php';
            if ($fs->exists($configurationFile)) {
                $portal = $this->getPortalInfoFromConfiguration($configurationFile);
            } else {
                $configurationFile = $appPath.'/main/inc/conf/configuration.php';
                $portal = $this->getPortalInfoFromConfiguration($configurationFile);
            }

            if (!empty($portal)) {
                $portals[] = $portal;
            }
        }

        $table = new Table($output);
        $table
            ->setHeaders(['Portal', 'Version', 'Packager', 'Configuration file'])
            ->setRows($portals)
        ;

        $table->render();

        return null;
    }
}
