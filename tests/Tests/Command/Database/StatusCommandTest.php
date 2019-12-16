<?php

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class StatusCommandTest extends KernelTestCase
{
    public function testExecute()
    {
        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('chash:chamilo_status');
        $commandTester = new CommandTester($command);

        $commandTester->execute([]);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        //$this->assertContains('Username: Wouter', $output);
    }
}
