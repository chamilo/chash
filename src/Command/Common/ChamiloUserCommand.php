<?php

namespace Chash\Command\Common;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ChamiloUserCommand extends DatabaseCommand
{
    protected function configure(): void
    {
        parent::configure();
    }

    /**
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
    }
}
