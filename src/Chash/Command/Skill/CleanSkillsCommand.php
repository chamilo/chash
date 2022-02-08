<?php

namespace Chash\Command\Skill;

use Chash\Command\Database\CommonDatabaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

/**
 * Class CleanSkillsCommand
 * Definition of the skill:clean command
 * Remove all skills (except root) to return to initial state
 * @package Chash\Command\Skill
 */
class CleanSkillsCommand extends CommonDatabaseCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('skill:clean')
            ->setAliases(array('skc'))
            ->setDescription('Removes all skills (and related resources) from the database to get clean state')
            ->addOption(
                'keep-badges',
                null,
                InputOption::VALUE_NONE,
                'Do not delete PNG badges in app/upload/badges'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $_configuration = $this->getHelper('configuration')->getConfiguration();
        $conn = $this->getConnection($input);
        $keepBadges = $input->getOption('keep-badges'); //1 if the option was set
        if ($conn instanceof \Doctrine\DBAL\Connection) {
            // Clean all standard tables related with skills
            $queries = [
                "TRUNCATE skill_rel_user_comment",
                "DELETE FROM skill_rel_user",
                "DELETE FROM skill_level_profile",
                "DELETE FROM skill_level",
                "DELETE FROM skill_rel_gradebook",
                "DELETE FROM skill_rel_skill",
                "DELETE FROM skill WHERE id > 1",
                "UPDATE skill SET name = 'Root', short_code = 'root' WHERE id = 1",
                "INSERT INTO skill_rel_skill (id, skill_id, parent_id, relation_type, level) values (1, 1, 0, 0, 0)",
            ];
            foreach ($queries as $del) {
                try {
                    $stmt = $conn->prepare($del);
                    $stmt->executeQuery();
                } catch (\PDOException $e) {
                    $output->write('SQL error!'.PHP_EOL);
                    throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
                }
            }
            // These are optional tables in 1.11.x. Check if they are present. If so, empty them as well.
            $queriesIfExists = [
                'skill_rel_item_rel_user',
                'skill_rel_item',
                'skill_rel_course',
            ];
            foreach ($queriesIfExists as $table) {
                try {
                    $stmt = $conn->prepare("SELECT count(*) FROM information_schema.TABLES WHERE TABLE_NAME = '$table'");
                    $result = $stmt->executeQuery();
                    $tableExists = (bool) $result->fetchOne();
                } catch (\PDOException $e) {
                    $output->write('SQL error checking if table '.$table.' exists!'.PHP_EOL);
                    throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
                }
                if ($tableExists) {
                    try {
                        $stmt = $conn->prepare("TRUNCATE $table");
                        $stmt->executeQuery();
                    } catch (\PDOException $e) {
                        $output->write('SQL error!'.PHP_EOL);
                        throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
                    }
                }
            }
            // Some extra fields might have been created on skills (extra_field_type = 8). If so, empty them, except on root.
            $sqlExtraFields = 'DELETE FROM extra_field_values '.
                'WHERE field_id IN (SELECT id FROM extra_field WHERE extra_field_type = 8) '.
                'AND item_id > 1';
            try {
                $stmt = $conn->prepare($sqlExtraFields);
                $stmt->executeQuery();
            } catch (\PDOException $e) {
                $output->write('SQL error!'.PHP_EOL);
                throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
            }
            $sqlExtraFields = 'DELETE FROM extra_field_rel_tag '.
                'WHERE field_id IN (SELECT id FROM extra_field WHERE extra_field_type = 8 and field_type = 10) '.
                'AND item_id > 1';
            try {
                $stmt = $conn->prepare($sqlExtraFields);
                $stmt->executeQuery();
            } catch (\PDOException $e) {
                $output->write('SQL error!'.PHP_EOL);
                throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
            }
            $sqlTags = 'DELETE FROM tag '.
                'WHERE field_id IN (SELECT id FROM extra_field WHERE extra_field_type = 8 and field_type = 10)';
            try {
                $stmt = $conn->prepare($sqlTags);
                $stmt->executeQuery();
            } catch (\PDOException $e) {
                $output->write('SQL error!'.PHP_EOL);
                throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
            }
            if (!$keepBadges) {
                // Delete all badges icons in app/upload/badges
                $finder = new Finder();
                $sysPath = $this->getConfigurationHelper()->getSysPath();
                $filesAdded = false;
                if (is_dir($sysPath.'app/upload/badges')) {
                    $finder->in($sysPath.'app/upload/badges/');
                    $finder->files()->name('*.png');
                    $filesAdded = true;
                }
                if ($filesAdded) {
                    $this->removeFiles($finder, $output);
                    $output->writeln('PNG badges removed from app/upload/badges.');
                } else {
                    $output->writeln('No badges found to clean in app/upload/badges.');
                }
            } else {
                $output->writeln('PNG badges not removed from app/upload/badges.');
            }

            $output->writeln('All skills have been cleaned.');
        } else {
            $output->writeln('The connection does not seem to be a valid PDO connection');
        }
        return null;
    }
}
