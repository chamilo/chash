<?php

/**
 * Make sure you have this setting in your php.ini (cli)
 * phar.readonly = Off
 */
$phar = new Phar('chash.phar');
$phar->buildFromDirectory(__DIR__, '/\.php$/');
$phar->addFile('chash.php');
$phar->setStub($phar->createDefaultStub('chash.php'));
