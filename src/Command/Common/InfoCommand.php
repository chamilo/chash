<?php

namespace Chash\Command\Common;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class InfoCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption(
                'conf',
                null,
                InputOption::VALUE_NONE,
                'Set a configuration file'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configuration = $input->getOption('conf');
        $this->getHelper('configuration')->readConfigurationFile($configuration);

        return 0;
    }
}
