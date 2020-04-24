<?php

namespace Chash\Command\User;

use Chash\Command\Database\CommonDatabaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class CommonChamiloUserCommand.
 */
class CommonChamiloUserCommand extends CommonDatabaseCommand
{
    protected function configure()
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
