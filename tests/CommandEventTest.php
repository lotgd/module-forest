<?php
declare(strict_types=1);

namespace LotGD\Module\Forest\Tests;

use LotGD\Core\Console\Command\Module\ModuleConfigListCommand;
use LotGD\Core\Console\Command\Module\ModuleConfigSetCommand;
use LotGD\Core\Models\ModuleProperty;
use LotGD\Module\Forest\Module;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CommandEventTest extends ModuleTestCase
{
    public function useSilentHandler(): bool
    {
        return true;
    }

    public function testIfModuleSettingsGetAddedToListSettingCommand()
    {
        $command = new CommandTester(new ModuleConfigListCommand($this->g));
        $command->execute(["moduleName" => Module::Module]);
        $output = $command->getDisplay();

        $this->assertSame(Command::SUCCESS, $command->getStatusCode());
        $this->assertStringContainsString(Module::ExperienceBonusFactorProperty, $output);
        $this->assertStringContainsString("0.25", $output);
        $this->assertStringContainsString(Module::ExperienceMalusFactorProperty, $output);
    }

    public function testIfBonusExperienceSettingGetsChangedAfterSettingItWithModuleConfigSetCommand()
    {
        $command = new CommandTester(new ModuleConfigSetCommand($this->g));
        $command->execute([
            "moduleName" => Module::Module,
            "setting" => Module::ExperienceBonusFactorProperty,
            "value" => 0.33,
        ]);
        $output = $command->getDisplay();

        // Assert display
        $this->assertSame(Command::SUCCESS, $command->getStatusCode());
        $this->assertStringContainsString("[OK]", $output);
        $this->assertStringContainsString("Bonus experience factor was set to 0.33.", $output);

        // Assert values
        $this->assertSame(0.33, $this->moduleModel->getProperty(Module::ExperienceBonusFactorProperty, null));
    }

    public function testIfBonusExperienceSettingGetsChangedAndWarningIsShownAfterSettingItWithModuleConfigSetCommandAndNegativeValue()
    {
        $command = new CommandTester(new ModuleConfigSetCommand($this->g));
        $command->execute([
            "moduleName" => Module::Module,
            "setting" => Module::ExperienceBonusFactorProperty,
            "value" => -0.33,
        ]);
        $output = $command->getDisplay();

        // Assert display
        $this->assertSame(Command::SUCCESS, $command->getStatusCode());
        $this->assertStringContainsString("[WARNING]", $output);
        $this->assertStringContainsString("A negative bonus factor will lead to less experience earned.", $output);
        $this->assertStringContainsString("[OK]", $output);
        $this->assertStringContainsString("Bonus experience factor was set to -0.33.", $output);

        // Assert values
        $this->assertSame(-0.33, $this->moduleModel->getProperty(Module::ExperienceBonusFactorProperty, null));
    }

    public function testIfMalusExperienceSettingGetsChangedAfterSettingItWithModuleConfigSetCommand()
    {
        $command = new CommandTester(new ModuleConfigSetCommand($this->g));
        $command->execute([
            "moduleName" => Module::Module,
            "setting" => Module::ExperienceMalusFactorProperty,
            "value" => 0.33,
        ]);
        $output = $command->getDisplay();

        // Assert display
        $this->assertSame(Command::SUCCESS, $command->getStatusCode());
        $this->assertStringContainsString("[OK]", $output);
        $this->assertStringContainsString("Malus experience factor was set to 0.33.", $output);

        // Assert values
        $this->assertSame(0.33, $this->moduleModel->getProperty(Module::ExperienceMalusFactorProperty, null));
    }

    public function testIfMalusExperienceSettingGetsChangedAndWarningIsShownAfterSettingItWithModuleConfigSetCommandAndNegativeValue()
    {
        $command = new CommandTester(new ModuleConfigSetCommand($this->g));
        $command->execute([
            "moduleName" => Module::Module,
            "setting" => Module::ExperienceMalusFactorProperty,
            "value" => -0.33,
        ]);
        $output = $command->getDisplay();

        // Assert display
        $this->assertSame(Command::SUCCESS, $command->getStatusCode());
        $this->assertStringContainsString("[WARNING]", $output);
        $this->assertStringContainsString("A negative malus factor will lead to more experience earned.", $output);
        $this->assertStringContainsString("[OK]", $output);
        $this->assertStringContainsString("Malus experience factor was set to -0.33.", $output);

        // Assert values
        $this->assertSame(-0.33, $this->moduleModel->getProperty(Module::ExperienceMalusFactorProperty, null));
    }

    public function testIfMalusExperienceCannotBeSetHigherThanOne()
    {
        $command = new CommandTester(new ModuleConfigSetCommand($this->g));
        $command->execute([
            "moduleName" => Module::Module,
            "setting" => Module::ExperienceMalusFactorProperty,
            "value" => 1.5,
        ]);
        $output = $command->getDisplay();

        // Assert display
        $this->assertSame(Command::SUCCESS, $command->getStatusCode());
        $this->assertStringContainsString("[ERROR]", $output);
        $this->assertStringContainsString("Experience malus cannot be bigger than 1. This would lead to the character loosing experience in total.", $output);

        $this->getEntityManager()->clear();
    }
}