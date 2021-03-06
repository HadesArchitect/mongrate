<?php

namespace Mongrate\Tests\Command;

use Mongrate\Command\DownCommand;
use Mongrate\Enum\DirectionEnum;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Parser;

class DownCommandTest extends BaseCommandTest
{
    public function setUp()
    {
        parent::setUp();

        $companies = $this->db->selectCollection('Company');
        $companies->upsert([], ['name' => 'Bob', 'address' => ['streetFirstLine' => 'Lena Gardens']]);

        $migrations = $this->db->selectCollection('MongrateMigrations');
        $migrations->upsert(['className' => 'UpdateAddressStructure'], ['$set' => ['isApplied' => true]]);
    }

    public function testExecute()
    {
        $application = new Application();
        $application->add(new DownCommand(null, $this->parametersFromYmlFile));
        $command = $application->find(DirectionEnum::DOWN);
        $commandTester = new CommandTester($command);
        $collection = $this->db->selectCollection('Company');

        // Starts out with an address at the root of 'address'.
        $this->assertEquals('Lena Gardens', $collection->findOne(['name' => 'Bob'])['address']['streetFirstLine']);

        // Run the command.
        $commandTester->execute(['command' => $command->getName(), 'name' => 'UpdateAddressStructure']);
        $this->assertEquals(
            "Migrating down... UpdateAddressStructure\n"
            ."Migrated down\n",
            $commandTester->getDisplay()
        );

        // Now has an array of addresses at the root of 'address'.
        $this->assertArrayHasKey(0, $collection->findOne(['name' => 'Bob'])['address']);
        $this->assertEquals('Lena Gardens', $collection->findOne(['name' => 'Bob'])['address'][0]['streetFirstLine']);
    }

    /**
     * @expectedException Mongrate\Exception\MigrationDoesntExist
     * @expectedExceptionMessage There is no migration called "Elvis" in "resources/examples/Elvis/Migration.php"
     */
    public function testExecute_migrationDoesntExist()
    {
        $application = new Application();
        $application->add(new DownCommand(null, $this->parametersFromYmlFile));

        $command = $application->find(DirectionEnum::DOWN);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName(), 'name' => 'Elvis']);
    }

    public function testExecute_cannotApply()
    {
        $application = new Application();
        $application->add(new DownCommand(null, $this->parametersFromYmlFile));
        $command = $application->find(DirectionEnum::DOWN);
        $commandTester = new CommandTester($command);

        $commandTester->execute(['command' => $command->getName(), 'name' => 'UpdateAddressStructure']);
        $this->assertContains("Migrated down", $commandTester->getDisplay());

        $this->setExpectedException('Mongrate\Exception\CannotApplyException', 'Cannot go down - the migration is not applied yet.');
        $commandTester->execute(['command' => $command->getName(), 'name' => 'UpdateAddressStructure']);
    }

    public function testExecute_forceApply()
    {
        $application = new Application();
        $application->add(new DownCommand(null, $this->parametersFromYmlFile));
        $command = $application->find(DirectionEnum::DOWN);
        $commandTester = new CommandTester($command);

        $commandTester->execute(['command' => $command->getName(), 'name' => 'UpdateAddressStructure']);
        $this->assertContains("Migrated down", $commandTester->getDisplay());

        $commandTester->execute(['command' => $command->getName(), 'name' => 'UpdateAddressStructure', '-f' => true]);
        $this->assertContains("Migrated down", $commandTester->getDisplay());
    }
}
