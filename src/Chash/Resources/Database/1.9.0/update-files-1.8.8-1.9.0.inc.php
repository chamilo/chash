<?php

/* For licensing terms, see /license.txt */

$updateFiles = function($_configuration, $mainConnection, $courseList, $dryRun, $output){

    $conf_dir            = $this->getConfigurationPath();
    $portfolio_conf_dist = $conf_dir.'portfolio.conf.dist.php';
    $portfolio_conf      = $conf_dir.'portfolio.conf.php';

    if (!file_exists($portfolio_conf)) {
        if (file_exists($portfolio_conf_dist)) {
            copy($portfolio_conf_dist, $portfolio_conf);
        }
    }

    // Adds events.conf file.
    if (!file_exists($conf_dir.'events.conf.php')) {
        if (file_exists($conf_dir.'events.conf.dist.php')) {
            copy($conf_dir.'events.conf.dist.php', $conf_dir.'events.conf.php');
        }
    }

    if (!file_exists($conf_dir.'add_course.conf.php')) {
        if (file_exists($conf_dir.'add_course.conf.dist.php')) {
            copy($conf_dir.'add_course.conf.dist.php', $conf_dir.'add_course.conf.php');
        }
    }

};

