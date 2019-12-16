<?php

namespace Chash\Command\Email;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Exception;

/**
 * Class SendEmailCommand
 * Changes a user password to the one given
 * Command functions meant to deal with what the user of this script is calling
 * it for.
 */
class SendEmailCommand extends CommonChamiloEmailCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('email:send_email')
            ->setDescription('Sends email using Chamilo e-mail system')
            ->addArgument(
                'recipient-name',
                InputArgument::REQUIRED,
                'Recipient name'
            )
            ->addArgument(
                'recipient-email',
                InputArgument::REQUIRED,
                'Recipient e-mail'
            )
            ->addArgument(
                'subject',
                InputArgument::REQUIRED,
                'Email subject'
            )
            ->addArgument(
                'message',
                InputArgument::REQUIRED,
                'Message'
            )
            ->addArgument(
                'sender-name',
                InputArgument::OPTIONAL,
                'Sender name',
                ''
            )
            ->addArgument(
                'sender-email',
                InputArgument::OPTIONAL,
                'Sender mail',
                ''
            )
            ->addArgument(
                'extra-headers',
                InputArgument::OPTIONAL,
                'Extra headers',
                ''
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $mailLib = $this->getHelper('configuration')->getLibFile('mail.lib.inc.php');
        $mainApiLib = $this->getHelper('configuration')->getLibFile('main_api.lib.php');
        $cnfFiles = $this->getHelper('configuration')->getConfFile('mail.conf.php');
        $conn = $this->getConnection($input);

        if (empty($mailLib)) {
            $output->writeln('We could not find the mail.lib.inc.php file');
        } else {
            global $_configuration, $platform_email;
            $_configuration = $this->getHelper('configuration')->getConfiguration();

            $this->getHelper('configuration')->getConnection();
            $userTable = $_configuration['main_database'].'.user';
            $adminTable = $_configuration['main_database'].'.admin';

            require_once "SendEmailCommand.php";
            require_once "SendEmailCommand.php";
            require_once "SendEmailCommand.php";

            $recipient_name = $input->getArgument('recipient-name');
            $recipient_email = $input->getArgument('recipient-email');
            $subject = $input->getArgument('subject');
            $message = $input->getArgument('message');
            $sender_name = $input->getArgument('sender-name');
            $sender_email = $input->getArgument('sender-email');
            $extra_headers = $input->getArgument('extra-headers');

            if ($conn instanceof \Doctrine\DBAL\Connection) {
                $sql = "SELECT email, CONCAT(lastname, firstname) as name FROM $userTable u "
                     . "LEFT JOIN $adminTable a ON a.user_id = u.user_id "
                     . "ORDER BY u.user_id LIMIT 1";
                try {
                    $stmt = $conn->prepare($sql);
                    $stmt->execute();
                } catch (\PDOException $e) {
                    $output->write('SQL error!' . PHP_EOL);
                    throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
                }
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if (empty($sender_name)) {
                    $sender_name = $row['name'];
                }
                if (empty($sender_email)) {
                    $sender_email = $row['email'];
                }

                try {
                    $output->writeln('Your message is going to be sent ...');
                    $rsp = api_mail_html($recipient_name, $recipient_email, $subject, $message, $sender_name, $sender_email, $extra_headers);
                    if ($rsp) {
                        $output->writeln('Your message has been sent correctly');
                    } else {
                        $output->writeln('Your message could NOT be sent correctly');
                        $output->writeln('Check the recipient email or your chamilo configuration');
                    }
                } catch (Exception $e) {
                    $output->writeln('We have detected some problems');
                    $output->writeln($e->getMessage());
                }
            } else {
                $output->writeln('The connection does not seem to be a valid PDO connection');
            }
        }
        return null;
    }
}
