<?php

namespace Chash\Command\User;

use Chash\Command\Common\DatabaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command functions meant to deal with what the user of this script is calling
 * it for.
 */
/**
 * Class AddUserCommand
 * Changes a user password to the one given.
 */
class AddUserCommand extends DatabaseCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('user:add_user')
            ->setDescription('Add a new user')
            ->addArgument(
                'firstname',
                InputArgument::REQUIRED,
                'Allows you to specify the firstname'
            )
            ->addArgument(
                'lastname',
                InputArgument::REQUIRED,
                'Allows you to specify the lastname'
            )
            ->addArgument(
                'username',
                InputArgument::REQUIRED,
                'Allows you to specify the username'
            )
            ->addArgument(
                'email',
                InputArgument::REQUIRED,
                'Allows you to specify the email'
            )
            ->addArgument(
                'password',
                InputArgument::REQUIRED,
                'The new password to give this user'
            )
            ->addArgument(
                'role',
                InputArgument::OPTIONAL,
                'The user role: anonymous, student (default), teacher, admin'
            )
            ->addArgument(
                'language',
                InputArgument::OPTIONAL,
                'The user language (in English). Defaults to "english". Make sure it is available.'
            );
    }

    /**
     * @return void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $conn = $this->getConnection($input);
        $_configuration = $this->getHelper('configuration')->getConfiguration();
        $firstname = $input->getArgument('firstname');
        $lastname = $input->getArgument('lastname');
        $username = $input->getArgument('username');
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');
        $role = $input->getArgument('role');
        if (empty($role)) {
            $role = 'student';
        }
        $language = $input->getArgument('language');
        if (empty($language)) {
            $language = 'english';
        }
        if ($conn instanceof \Doctrine\DBAL\Connection) {
            try {
                $userSelect = 'SELECT * FROM user WHERE username = '.$conn->quote($username);
                $stmt = $conn->prepare($userSelect);
                $stmt->execute();
                $un = $stmt->rowCount();
            } catch (\PDOException $e) {
                $output->write('SQL error!'.PHP_EOL);

                throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
            }

            if (0 === $un) {
                $enc = $_configuration['password_encryption'];
                $salt = sha1(uniqid(null, true));
                switch ($enc) {
                    case 'bcrypt':
                        $password = $conn->quote(password_hash($password, PASSWORD_BCRYPT, ['cost' => 4, 'salt' => $salt]));

                        break;
                    case 'sha1':
                        $password = $conn->quote(sha1($password));

                        break;
                    case 'md5':
                        $password = $conn->quote(md5($password));

                        break;
                    default:
                        $password = $conn->quote($password);

                        break;
                }
                $numRole = 5;
                $isAdmin = 0;
                $stringRoles = '';
                switch ($role) {
                    case 'anonymous':
                        $numRole = 6;

                        break;
                    case 'teacher':
                        $numRole = 1;

                        break;
                    case 'admin':
                        $numRole = 1;
                        $isAdmin = 1;
                        $stringRoles = 'a:1:{i:0;s:16:"ROLE_SUPER_ADMIN";}';

                        break;
                    case 'student':
                    default:
                        $numRole = 5;
                }
                // @TODO make UTC
                $time = date('Y-m-d h:i:s');
                $expiration = time() + (60 * 60 * 24 * 366 * 10);
                $timeExpiry = date('Y-m-d h:i:s', $expiration);
                $firstname = $conn->quote($firstname);
                $lastname = $conn->quote($lastname);
                $username = $conn->quote($username);
                $email = $conn->quote($email);
                $language = $conn->quote($language);
                $ups = "INSERT INTO user (
                    firstname, 
                    lastname, 
                    username,
                    username_canonical,
                    email,
                    email_canonical,
                    salt,
                    password, 
                    status,
                    roles,
                    enabled,
                    active, 
                    auth_source, 
                    creator_id,
                    registration_date,
                    expiration_date,
                    language
                  ) VALUES (
                    '$firstname', 
                    '$lastname', 
                    '$username', 
                    '$username', 
                    '$email',
                    '$email',
                    '$salt', 
                    $password, 
                    $numRole,
                    '$stringRoles',
                    1, 
                    1, 
                    'platform',
                    1,
                    '$time',
                    '$timeExpiry',
                    $language
                  )";

                try {
                    $stmt = $conn->prepare($ups);
                    $stmt->execute();
                    $newUserId = $conn->lastInsertId();
                } catch (\PDOException $e) {
                    $output->write('SQL error!'.PHP_EOL);

                    throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
                }

                $output->writeln('User '.$username.' has been created.');
                if (1 === $isAdmin) {
                    $uas = "INSERT INTO admin (user_id) values ($newUserId)";

                    try {
                        $stmt = $conn->prepare($uas);
                        $stmt->execute();
                    } catch (\PDOException $e) {
                        $output->write('SQL error!'.PHP_EOL);

                        throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
                    }
                }
                // Add user to access_url_rel_user
                $uas = "INSERT INTO access_url_rel_user (access_url_id, user_id) values (1, $newUserId)";

                try {
                    $stmt = $conn->prepare($uas);
                    $stmt->execute();
                } catch (\PDOException $e) {
                    $output->write('SQL error!'.PHP_EOL);

                    throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
                }
                $uas = "UPDATE user SET user_id = id WHERE id = $newUserId";

                try {
                    $stmt = $conn->prepare($uas);
                    $stmt->execute();
                } catch (\PDOException $e) {
                    $output->write('SQL error!'.PHP_EOL);

                    throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
                }
            } else {
                $output->writeln('A user with username '.$username.' already exists');
            }
        } else {
            $output->writeln('The connection does not seem to be a valid PDO connection');
        }

        return null;
    }
}
