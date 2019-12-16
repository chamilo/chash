<?php

namespace Chash\Command\Database;

use Chash\Command\Common\DatabaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Connects to the MySQL client without the need to introduce a password.
 *
 * @return int Exit code returned by mysql command
 */
class RunSQLCommand extends DatabaseCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('db:sql_cli')
            ->setAliases(['dbc', 'dbcli'])
            ->setDescription('Enters to the SQL command line');
        $this->setHelp('Prompts a SQL cli');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);
        $output->writeln('Starting Chamilo SQL cli');

        $_configuration = $this->getConfigurationArray();

        $cmd = 'mysql -h '.$_configuration['db_host'].' -u '.$_configuration['db_user'].' -p'.$_configuration['db_password'].' '.$_configuration['main_database'];
        $process = proc_open($cmd, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes);
        $proc_status = proc_get_status($process);
        $exit_code = proc_close($process);

        return $proc_status['running'] ? $exit_code : $proc_status['exitcode'];

        /*$output->writeln('<comment>Starting Chamilo process</comment>');
        $output->writeln('<info>Chamilo process ended succesfully</info>');
        */
        /*
        $progress = $this->getHelperSet()->get('progress');

        $progress->start($output, 50);
        $i = 0;
        while ($i++ < 50) {
            // ... do some work

            // advance the progress bar 1 unit
            $progress->advance();
        }
        $progress->finish();*/

        // Inside execute function
        //$output->getFormatter()->setStyle('fcbarcelona', new OutputFormatterStyle('red', 'blue', array('blink', 'bold', 'underscore')));
        //$output->writeln('<fcbarcelona>Messi for the win</fcbarcelona>');

        return 0;
    }
}
