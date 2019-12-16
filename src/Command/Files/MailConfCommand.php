<?php

namespace Chash\Command\Files;

use Chash\Command\Common\DatabaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class MailConfCommand
 * Returns the current mail configuration.
 */
class MailConfCommand extends DatabaseCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('files:show_mail_conf')
            ->setDescription('Returns the current mail config');
    }

    /**
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);
        $this->writeCommandHeader($output, 'Current mail configuration.');

        $path = $this->getHelper('configuration')->getConfigurationPath();
        $path .= 'mail.conf.php';
        define('IS_WINDOWS_OS', 'win' == strtolower(substr(php_uname(), 0, 3)) ? true : false);
        $platform_email = [];
        if (isset($path) && is_file($path)) {
            $output->writeln('File: '.$path);
            $lines = file($path);
            $list = ['SMTP_HOST', 'SMTP_PORT', 'SMTP_MAILER', 'SMTP_AUTH', 'SMTP_USER', 'SMTP_PASS'];
            foreach ($lines as $line) {
                $match = [];
                if (preg_match("/platform_email\['(.*)'\]/", $line, $match)) {
                    if (in_array($match[1], $list)) {
                        eval($line);
                    }
                }
            }
            // @todo $platform_email is not set
            $output->writeln('Host:     '.$platform_email['SMTP_HOST']);
            $output->writeln('Port:     '.$platform_email['SMTP_PORT']);
            $output->writeln('Mailer:   '.$platform_email['SMTP_MAILER']);
            $output->writeln('Auth SMTP:'.$platform_email['SMTP_AUTH']);
            $output->writeln('User:     '.$platform_email['SMTP_USER']);
            $output->writeln('Pass:     '.$platform_email['SMTP_PASS']);
        } else {
            $output->writeln('<comment>Nothing to print</comment>');
        }

        return 0;
    }
}
