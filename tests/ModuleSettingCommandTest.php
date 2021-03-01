<?php
declare(strict_types=1);

namespace LotGD\Module\Forest\Tests;

use LotGD\Core\Console\Command\Module\ModuleConfigListCommand;
use LotGD\Core\Console\Command\Module\ModuleConfigResetCommand;
use LotGD\Core\Console\Command\Module\ModuleConfigSetCommand;
use LotGD\Core\Models\ModuleProperty;
use LotGD\Module\Forest\Module;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ModuleSettingCommandTest extends ModuleTestCase
{
    public function testIfModuleSettingsGetAddedToListSettingCommand()
    {
        $command = new CommandTester(new ModuleConfigListCommand($this->g));
        $command->execute(["moduleName" => Module::Module]);
        $output = $command->getDisplay();

        $this->assertSame(Command::SUCCESS, $command->getStatusCode());
        $this->assertStringContainsString(Module::ExperienceBonusFactorProperty, $output);
        $this->assertStringContainsString((string)Module::ExperienceBonusFactorPropertyDefault, $output);
        $this->assertStringContainsString(Module::ExperienceMalusFactorProperty, $output);
        $this->assertStringContainsString((string)Module::ExperienceMalusFactorPropertyDefault, $output);
        $this->assertStringContainsString(Module::LostExperienceUponDeathProperty, $output);
        $this->assertStringContainsString((string)Module::LostExperienceUponDeathPropertyDefault, $output);
        $this->assertStringContainsString(Module::GemDropProbabilityProperty, $output);
        $this->assertStringContainsString((string)Module::GemDropProbabilityPropertyDefault, $output);
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

    public function testIfBonusExperienceSettingGetsResetAfterSettingItWithModuleConfigReetCommand()
    {
        $command = new CommandTester(new ModuleConfigResetCommand($this->g));
        $command->execute([
            "moduleName" => Module::Module,
            "setting" => Module::ExperienceBonusFactorProperty,
        ]);
        $output = $command->getDisplay();

        // Assert display
        $this->assertSame(Command::SUCCESS, $command->getStatusCode());
        $this->assertStringContainsString("[OK]", $output);
        $this->assertStringContainsString("Experience bonus factor was reset to 0.25.", $output);

        // Assert values
        $this->assertSame(0.25, $this->moduleModel->getProperty(Module::ExperienceBonusFactorProperty, null));
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

    public function testIfMalusExperienceSettingGetsResetAfterSettingItWithModuleConfigResetCommand()
    {
        $command = new CommandTester(new ModuleConfigResetCommand($this->g));
        $command->execute([
            "moduleName" => Module::Module,
            "setting" => Module::ExperienceMalusFactorProperty,
        ]);
        $output = $command->getDisplay();

        // Assert display
        $this->assertSame(Command::SUCCESS, $command->getStatusCode());
        $this->assertStringContainsString("[OK]", $output);
        $this->assertStringContainsString("Experience malus factor was reset to 0.25.", $output);

        // Assert values
        $this->assertSame(0.25, $this->moduleModel->getProperty(Module::ExperienceMalusFactorProperty, null));
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

    public function testIfGemDropProbabilityGetsChangedAfterSettingItWithModuleConfigSetCommand()
    {
        $command = new CommandTester(new ModuleConfigSetCommand($this->g));
        $command->execute([
            "moduleName" => Module::Module,
            "setting" => Module::GemDropProbabilityProperty,
            "value" => 0.2,
        ]);
        $output = $command->getDisplay();

        // Assert display
        $this->assertSame(Command::SUCCESS, $command->getStatusCode());
        $this->assertStringContainsString("[OK]", $output);
        $this->assertStringContainsString("Gem drop probability was set to 0.2.", $output);

        // Assert values
        $this->assertSame(0.2, $this->moduleModel->getProperty(Module::GemDropProbabilityProperty, null));
    }

    public function testIfGemDropProbabilityGetsResetAfterResettingItWithModuleConfigResetCommand()
    {
        $command = new CommandTester(new ModuleConfigResetCommand($this->g));
        $command->execute([
            "moduleName" => Module::Module,
            "setting" => Module::GemDropProbabilityProperty,
        ]);
        $output = $command->getDisplay();

        // Assert display
        $this->assertSame(Command::SUCCESS, $command->getStatusCode());
        $this->assertStringContainsString("[OK]", $output);
        $this->assertStringContainsString("Gem drop probability was reset to 0.04.", $output);

        // Assert values
        $this->assertSame(Module::GemDropProbabilityPropertyDefault, $this->moduleModel->getProperty(Module::GemDropProbabilityProperty, null));
    }

    public function testIfGemDropProbabilityCannotGetChangedAfterSettingItWithModuleConfigSetCommandToLowerThan0()
    {
        $command = new CommandTester(new ModuleConfigSetCommand($this->g));
        $command->execute([
            "moduleName" => Module::Module,
            "setting" => Module::GemDropProbabilityProperty,
            "value" => -1,
        ]);
        $output = $command->getDisplay();

        // Assert display
        $this->assertSame(Command::SUCCESS, $command->getStatusCode());
        $this->assertStringContainsString("[ERROR]", $output);
        $this->assertStringContainsString("Gem drop probability cannot be smaller than 0.", $output);

        // Assert values
        $this->assertNull($this->moduleModel->getProperty(Module::GemDropProbabilityProperty, null));
    }

    public function testIfGemDropProbabilityCannotGetChangedAfterSettingItWithModuleConfigSetCommandToBiggerThanOne()
    {
        $command = new CommandTester(new ModuleConfigSetCommand($this->g));
        $command->execute([
            "moduleName" => Module::Module,
            "setting" => Module::GemDropProbabilityProperty,
            "value" => 2,
        ]);
        $output = $command->getDisplay();

        // Assert display
        $this->assertSame(Command::SUCCESS, $command->getStatusCode());
        $this->assertStringContainsString("[ERROR]", $output);
        $this->assertStringContainsString("Gem drop probability cannot be higher than 1.", $output);

        // Assert values
        $this->assertNull($this->moduleModel->getProperty(Module::GemDropProbabilityProperty, null));
    }

    public function testIfLostExperienceUponDeathGetsChangedAfterSettingItWithModuleConfigSetCommand()
    {
        $command = new CommandTester(new ModuleConfigSetCommand($this->g));
        $command->execute([
            "moduleName" => Module::Module,
            "setting" => Module::LostExperienceUponDeathProperty,
            "value" => 0.5,
        ]);
        $output = $command->getDisplay();

        // Assert display
        $this->assertSame(Command::SUCCESS, $command->getStatusCode());
        $this->assertStringContainsString("[OK]", $output);
        $this->assertStringContainsString("Lost experience factor was set to 0.5.", $output);

        // Assert values
        $this->assertSame(0.5, $this->moduleModel->getProperty(Module::LostExperienceUponDeathProperty, null));
    }

    public function testIfLostExperienceUponDeathGetsResetAfterResettingItWithModuleConfigResetCommand()
    {
        $command = new CommandTester(new ModuleConfigResetCommand($this->g));
        $command->execute([
            "moduleName" => Module::Module,
            "setting" => Module::LostExperienceUponDeathProperty,
        ]);
        $output = $command->getDisplay();

        // Assert display
        $this->assertSame(Command::SUCCESS, $command->getStatusCode());
        $this->assertStringContainsString("[OK]", $output);
        $this->assertStringContainsString("Lost experience factor was reset to 0.1.", $output);

        // Assert values
        $this->assertSame(Module::LostExperienceUponDeathPropertyDefault, $this->moduleModel->getProperty(Module::LostExperienceUponDeathProperty, null));
    }

    public function testIfLostExperienceUponDeathCannotGetChangedAfterSettingItWithModuleConfigSetCommandToLowerThan0()
    {
        $command = new CommandTester(new ModuleConfigSetCommand($this->g));
        $command->execute([
            "moduleName" => Module::Module,
            "setting" => Module::LostExperienceUponDeathProperty,
            "value" => -1,
        ]);
        $output = $command->getDisplay();

        // Assert display
        $this->assertSame(Command::SUCCESS, $command->getStatusCode());
        $this->assertStringContainsString("[ERROR]", $output);
        $this->assertStringContainsString("Lost experience fraction must be at least 0. ", $output);

        // Assert values
        $this->assertNull($this->moduleModel->getProperty(Module::LostExperienceUponDeathProperty, null));
    }

    public function testIfLostExperienceUponDeathCannotGetChangedAfterSettingItWithModuleConfigSetCommandToBiggerThanOne()
    {
        $command = new CommandTester(new ModuleConfigSetCommand($this->g));
        $command->execute([
            "moduleName" => Module::Module,
            "setting" => Module::LostExperienceUponDeathProperty,
            "value" => 2,
        ]);
        $output = $command->getDisplay();

        // Assert display
        $this->assertSame(Command::SUCCESS, $command->getStatusCode());
        $this->assertStringContainsString("[ERROR]", $output);
        $this->assertStringContainsString("Character cannot loose more experience than he has.", $output);

        // Assert values
        $this->assertNull($this->moduleModel->getProperty(Module::LostExperienceUponDeathProperty, null));
    }
}