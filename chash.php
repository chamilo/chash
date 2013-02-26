#!/usr/bin/php5
<?php
/**
 * Command-line tool to do things more swiftly in Chamilo.
 * This script is inspired by the Drush module for Drupal, although it doesn't
 * re-use code from it.
 * To add support for a new command, create a chash_command_* function and add
 * comments to the _chash_usage() function.
 * @author Yannick Warnier <yannick.warnier@beeznest.com>
 * @version 1.1
 * @license This script is provided under the terms of the GNU/GPLv3+ license
 */
/**
 * Security check: do not allow any other calling method than command-line
 */
if (PHP_SAPI != 'cli') {
    die(_t("Chash cannot be called by any other method than the command line."));
}
/**
 * Return usage if not argument was passed
 */
if ($argc < 2) {
    //echo _t('You have to give at least one parameter')."\n";
    _chash_usage();
}
/**
 * Initialization: find the local Chamilo configuration file or thow error
 */
if (!$config_file = _chash_find_config_file()) {
    die(_t(
        "Couldn't find config file. Please either give the path to the Chamilo installation you want to act on, through the -c option, or 'cd' into a valid Chamilo installation directory"
    ));
}
/**
 * Include configuration file
 */
require $config_file;
/**
 * Controller: parse and deal with the options
 */
$options = _chash_parse_cli_options();
if (isset($options[0])) {
    if (function_exists('chash_command_'.$options[0])) {
        $func = 'chash_command_'.$options[0];
        array_shift($options);
        $func($options);
    } else {
        die(_t("We found no registered command matching your request"));
    }
} else {
    die(_t("No command given")."\n");
}
/**
 * Helper functions
 */
/**
 * Shows the usage documentation (all possible commands and the general syntax
 */
function _chash_usage()
{
    echo "\n";
    echo _t(
        "ChaSh goes for \"Chamilo Shell\".\nIt allows you to execute common administrative operations on a Chamilo LMS installation (1.9 or higher) from the command line."
    )."\n";
    echo _t(
        "ChaSh is developed by BeezNest, the Chamilo specialist corporation. See http://www.beeznest.com/ for contact details."
    )."\n";
    echo _t(
        'You can call chash.php with a series of commands. Each command has its own parameters. To run chash.php, you can either call it from inside a Chamilo directory (it will then find its way on its own) or from any other directory giving the path to the configuration file with --conf=/path/to/configuration.php'
    )."\n\n";
    echo _t('  Usage: php5 chash.php [command] [options]')."\n\n";
    // -- Commands explanation --
    echo _t('Available commands:')."\n";
    echo _t("  sql_cli\t\tEnters to the SQL command line")."\n";
    echo _t("  sql_dump\t\tOutputs a dump of the database")."\n";
    echo _t("  sql_restore\t\tInserts a database dump into the active database")."\n";
    echo _t("  sql_count\t\tOutputs a report about the number of rows in a table")."\n";
    echo _t("  full_backup\t\tGenerates a .tgz from the Chamilo files and database")."\n";
    echo _t("  clean_archives\tCleans the archives directory")."\n";
    echo _t("  drop_databases\tDrops all databases from the current Chamilo install")."\n";
    echo "\n";
    echo _t("Available options:")."\n";
    echo _t("  --conf=\tIndicates to chash where to find the configuration file of Chamilo.")."\n";
    echo "\n";
}

/**
 * Translate terms - currently incomplete implementation
 * @param string $term to translate
 * @param string $language to translate to
 * @return string Term's translation (or the original if no translation found)
 */
function _t($term, $language = 'english')
{
    return $term;
}

/**
 * Find the complete path to the Chamilo configuration file
 * @return string Path to the configuration file
 */
function _chash_find_config_file()
{
    global $argc, $argv;
    $found = false;
    if ($argc > 1) {
        $find = '--conf=';
        foreach ($argv as $arg) {
            if (substr($arg, 0, 7) == $find) {
                if (is_file(substr($arg, 7))) {
                    $found = substr($arg, 7);
                    break;
                }
                if (substr($arg, -1, 1) == '/') {
                    $arg = substr($arg, 0, -1);
                }
                if (is_file(substr($arg, 7).'/configuration.php')) {
                    $found = substr($arg, 7).'/configuration.php';
                    break;
                }
                if (is_file(substr($arg, 7).'/main/inc/conf/configuration.php')) {
                    $found = substr($arg, 7).'/main/inc/conf/configuration.php';
                    break;
                }
            }
        }
    }
    if (!$found) {
        $dir = getcwd();
        for ($i = 0; $i < 10; $i++) {
            $dir = realpath($dir);
            if (is_file($dir.'/configuration.php')) {
                $found = $dir.'/configuration.php';
                break;
            } elseif (is_file($dir.'/conf/configuration.php')) {
                $found = $dir.'/conf/configuration.php';
                break;
            } elseif (is_file($dir.'/inc/conf/configuration.php')) {
                $found = $dir.'/inc/conf/configuration.php';
                break;
            } elseif (is_file($dir.'/main/inc/conf/configuration.php')) {
                $found = $dir.'/main/inc/conf/configuration.php';
                break;
            } else {
                $dir = $dir.'/../';
            }
        }
    }
    return $found;
}

/**
 * Parse the command-line options into a usable array
 */
function _chash_parse_cli_options()
{
    $options = array();
    global $argv;
    array_shift($argv);
    foreach ($argv as $idx => $arg) {
        if (substr($arg, 0, 2) == '--') {
            //--option=value format
            if (strpos($arg, '=') > 1) {
                //found an equal sign
                list($option, $value) = explode('=', substr($arg, 2));
            } else {
                //no equal sign. Assuming --option-alone format
                $option = substr($arg, 2);
                $value = true;
            }
            $options[$option] = $value;
        } elseif (substr($arg, 0, 1) == '-') {
            //-optionvalue format
            $option = substr($arg, 1, 1);
            $value = substr($arg, 2);
            $options[$option] = $value;
        } else {
            //single param format
            $options[$idx] = $arg;
        }
    }
//  print_r($options);
    return $options;
}

/**
 * Connect to the database
 * @return object Database handler
 */
function _chash_db_connect($conf)
{
    $dbh = mysql_connect($conf['db_host'], $conf['db_user'], $conf['db_password']);
    if (!$dbh) {
        die('Could not connect to server: '.mysql_error());
    }
    $db = mysql_select_db($conf['main_database'], $dbh);
    if (!$db) {
        die('Could not connect to database: '.mysql_error());
    }
    return $dbh;
}

/**
 * Gets an array with all the databases (particularly useful for Chamilo <1.9)
 * @return mixed Array of databases
 */
function _chash_get_all_databases()
{
    global $_configuration;
    $dbs = array();
    $dbs[] = $_configuration['main_database'];
    if (!in_array($_configuration['statistics_database'], $dbs) && !empty($_configuration['statistics_database'])) {
        $dbs[] = $_configuration['statistics_database'];
    }
    if (!in_array($_configuration['scorm_database'], $dbs) && !empty($_configuration['scorm_database'])) {
        $dbs[] = $_configuration['scorm_database'];
    }
    if (!in_array(
        $_configuration['user_personal_database'],
        $dbs
    ) && !empty($_configuration['user_personal_database'])
    ) {
        $dbs[] = $_configuration['user_personal_database'];
    }
    $dbh = _chash_db_connect($_configuration);
    $t = $_configuration['main_database'].'.course';
    $sql = 'SELECT db_name from '.$t;
    $res = mysql_query($sql);
    if (mysql_num_rows($res) > 0) {
        while ($row = mysql_fetch_array($res)) {
            $dbs[] = $row['db_name'];
        }
    }
    return $dbs;
}

/**
 * Command functions meant to deal with what the user of this script is calling
 * it for.
 */
/**
 * Returns a dump of the database (caller should use an output redirect of some kind to store
 * to a file)
 */
function chash_command_sql_dump()
{
    global $_configuration;
    system(
        'mysqldump -h '.$_configuration['db_host'].' -u '.$_configuration['db_user'].' -p'.$_configuration['db_password'].' '.$_configuration['main_database']
    );
    return null;
}

/**
 * Imports an SQL dump of the database (caller should use an output redirect of some kind
 * to store to a file)
 * @param array $params params received
 */
function chash_command_sql_restore($params)
{
    global $_configuration;
    if (empty($params)) {
        echo _t('No parameters provided.')."\n";
        echo _t(
            'The sql_restore command allows you to restore an SQL dump right into the active database of a given Chamilo installation (which will also erase all previous data in that database, by the way.'
        )."\n";
        echo _t('To launch th full_backup command, the following parameter is required:')."\n";
        echo '  --dump'."\t"._t('Allows you to specify the dump\'s full path, e.g. --result=/tmp/dump.sql')."\n";
        return false;
    }
    system(
        'mysql -h '.$_configuration['db_host'].' -u '.$_configuration['db_user'].' -p'.$_configuration['db_password'].' '.$_configuration['main_database'].' < '.$params['dump']
    );
    return null;
}

/**
 * Make a full backup of the given/current install and put the results (files and db) into the given file. Store the temporary data into the /tmp/ directory
 * @param array $params The params received
 */
function chash_command_full_backup($params)
{
    global $_configuration, $config_file;
    $cha_dir = realpath(dirname($config_file).'/../../../');
    if (empty($params)) {
        echo _t('No parameters provided.')."\n";
        echo _t(
            'The full_backup command allows you to do a full backup of the files and database of a given Chamilo installation'
        )."\n";
        echo _t('To launch th full_backup command, the following parameters are available:')."\n";
        echo '  --result'."\t"._t('Allows you to specify a destination file, e.g. --result=/home/user/backup.tgz')."\n";
        echo '  --tmp'."\t"._t(
            'Allows you to specify in which temporary directory the backup files should be placed (optional, defaults to /tmp)'
        )."\n";
        echo '  --del-archive'."\t"._t(
            'Deletes the contents of the archive/ directory before the backup is executed'
        )."\n";
        return false;
    }
    if (empty($params['result'])) {
        echo "Please give us a result file, e.g. --result=/home/user/backup.tgz\n";
        return false;
    }
    $tmp = '/tmp';
    if (empty($params['tmp'])) {
        echo "No temporary directory defined. Assuming /tmp/. Please make sure you have enough space left on that device\n";
    } else {
        $tmp = $params['tmp'];
    }
    $del_archive = false;
    if (!empty($params['del-archive'])) {
        $del_archive = true;
        echo "Deleting contents of archive directory\n";
        chash_command_clean_archives();
    }
    $result_file = $params['result'];
    $f = $_configuration['db_user'];
    //backup the files (this requires root permissions)
    $bkp_dir = $tmp.'/'.$f.'-'.date('Ymdhis');
    $err = @mkdir($bkp_dir);
    $tgz = $bkp_dir.'/'.$f.'.tgz';
    $sql = $bkp_dir.'/'.$f.'-db.sql';
    $err = @system('tar zcf '.$tgz.' '.$cha_dir);
    $err = @system(
        'mysqldump -h '.$_configuration['db_host'].' -u '.$_configuration['db_user'].' -p'.$_configuration['db_password'].' '.$_configuration['main_database'].' --result-file='.$sql
    );
    $err = @system('tar zcf '.$result_file.' '.$bkp_dir);
    $err = @system('rm -rf '.$bkp_dir);
    //die('rm -rf '.$tgz.' '.$sql);
    return true;
}

/**
 * Count the number of rows in a specific table
 * @return mixed Integer number of rows, or null on error
 */
function chash_command_sql_count($params)
{
    global $_configuration;
    if (count($params) == 0 or !isset($params['t'])) {
        echo _t("Missing table name. Please give the name of the table with the -t option");
        return null;
    }
    $dbh =& _chash_db_connect($_configuration);
    $t = mysql_real_escape_string($params['t']);
    $q = mysql_query('SELECT COUNT(*) FROM '.$t);
    $r = mysql_fetch_row($q);
    $n = $r[0];
    echo _t('Database/table/number of rows: ').$_configuration['main_database'].'/'.$t.'/'.$n."\n";
    return $n;
}

/**
 * Clean the archives directory, leaving only index.html, twig and Serializer
 * @return bool True on success, false on error
 */
function chash_command_clean_archives()
{
    global $_configuration;
    if (empty($_configuration['root_sys'])) {
        echo _t(
            '$_configuration[\'root_sys\'] is empty. In these conditions, it is too dangerous to proceed with the deletion. Please ensure this variable is defined in main/inc/conf/configuration.php'
        )."\n";
        return false;
    }
    $dir = $_configuration['root_sys'].'/archive';
    $files = scandir($dir);
    foreach ($files as $file) {
        if (substr($file, 0, 1) == '.') {
            //ignore
        } elseif ($file == 'twig') {
            $err = @system('rm -rf '.$dir.'/twig/*');
        } elseif ($file == 'Serializer') {
            $err = @system('rm -rf '.$dir.'/Serializer/*');
        } else {
            $err = @system('rm -rf '.$dir.'/'.$file);
        }
    }
    echo _t("archive/ directory has been cleaned")."\n";
    return true;
}

/**
 * Connects to the MySQL client without the need to introduce a password
 * @return int Exit code returned by mysql command
 */
function chash_command_sql_cli()
{
    global $_configuration;
    $cmd = 'mysql -h '.$_configuration['db_host'].' -u '.$_configuration['db_user'].' -p'.$_configuration['db_password'].' '.$_configuration['main_database'];
    $process = proc_open($cmd, array(0 => STDIN, 1 => STDOUT, 2 => STDERR), $pipes);
    $proc_status = proc_get_status($process);
    $exit_code = proc_close($process);
    return ($proc_status["running"] ? $exit_code : $proc_status["exitcode"]);
}

/**
 * Drops all databases of the given installation
 */
function chash_command_drop_databases()
{
    global $_configuration;
    $cmd = 'mysql -h '.$_configuration['db_host'].' -u '.$_configuration['db_user'].' -p'.$_configuration['db_password'].' -e "DROP DATABASE %s"';
    $list = _chash_get_all_databases();
    if (is_array($list)) {
        foreach ($list as $db) {
            $c = sprintf($cmd, $db);
            echo "Dropping DB $db\n";
            $err = @system($c);
        }
    }
}