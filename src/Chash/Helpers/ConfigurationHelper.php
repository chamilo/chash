<?php

namespace Chash\Helpers;

use Symfony\Component\Console\Helper\Helper;

class ConfigurationHelper extends Helper
{
    protected $configuration;

    public function __construct()
    {
    }

    public function readConfigurationFile($path = null)
    {
        if (empty($path)) {
            $dir      = getcwd();
            $confFile = $dir.'/main/inc/conf/configuration.php';
            if (file_exists($confFile)) {
                require $confFile;
                $this->setConfiguration($_configuration);
                return $_configuration;
            }
        } else {
            if (file_exists($path)) {
                require $path;
                $this->setConfiguration($_configuration);
                return $_configuration;
            }
        }
        return false;
    }

    public function setConfiguration($configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * Connect to the database
     * @return object Database handler
     */
    public function getConnection()
    {
        $conf = $this->getConfiguration();
        $dbh  = mysql_connect($conf['db_host'], $conf['db_user'], $conf['db_password']);
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
    function getAllDatabases()
    {
        $_configuration = $this->getConfiguration();
        $dbs            = array();

        $dbs[] = $_configuration['main_database'];

        if (isset($_configuration['statistics_database']) && !in_array(
            $_configuration['statistics_database'],
            $dbs
        ) && !empty($_configuration['statistics_database'])
        ) {
            $dbs[] = $_configuration['statistics_database'];
        }
        if (isset($_configuration['scorm_database']) && !in_array(
            $_configuration['scorm_database'],
            $dbs
        ) && !empty($_configuration['scorm_database'])
        ) {
            $dbs[] = $_configuration['scorm_database'];
        }
        if (!in_array(
            $_configuration['user_personal_database'],
            $dbs
        ) && !empty($_configuration['user_personal_database'])
        ) {
            $dbs[] = $_configuration['user_personal_database'];
        }

        $connection = $this->getConnection();

        $t   = $_configuration['main_database'].'.course';
        $sql = 'SELECT db_name from '.$t;
        $res = mysql_query($sql);
        if (mysql_num_rows($res) > 0) {
            while ($row = mysql_fetch_array($res)) {
                if (!empty($row['db_name'])) {
                    $dbs[] = $row['db_name'];
                }
            }
        }
        return $dbs;
    }

    public function getConfiguration()
    {
        return $this->configuration;
    }

    public function getName()
    {
        return 'configuration';
    }
}
