<?php
declare(strict_types=1);

namespace LotGD\Module\Forest\Tests;

use LotGD\Core\Console\Command\Scene\SceneConfigListCommand;
use LotGD\Core\Console\Command\Scene\SceneConfigResetCommand;
use LotGD\Core\Console\Command\Scene\SceneConfigSetCommand;
use LotGD\Core\Models\Scene;
use LotGD\Module\Forest\Module;
use LotGD\Module\Forest\SceneTemplates\Forest;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class SceneSettingCommandTest extends ModuleTestCase
{
    public function useSilentHandler(): bool
    {
        return true;
    }

    public function getForestScene(): Scene
    {
        return $this->getEntityManager()->getRepository(Scene::class)->findOneBy(["template" => Forest::class]);
    }

    public function testIfModuleSettingsGetAddedToListSettingCommand()
    {
        $scene = $this->getForestScene();

        $command = new CommandTester(new SceneConfigListCommand($this->g));
        $command->execute(["id" => $scene->getId()]);
        $output = $command->getDisplay();

        $this->assertSame(Command::SUCCESS, $command->getStatusCode());
        $this->assertStringContainsString(Forest::LostExperienceUponDeathSceneProperty, $output);
        $this->assertStringContainsString(Forest::GemDropProbabilitySceneProperty, $output);
    }

    public function testIfGemDropProbabilityGetsChangedAfterSettingItWithSceneConfigSetCommand()
    {
        $scene = $this->getForestScene();

        $command = new CommandTester(new SceneConfigSetCommand($this->g));
        $command->execute([
            "id" => $scene->getId(),
            "setting" => Forest::GemDropProbabilitySceneProperty,
            "value" => 0.2,
        ]);
        $output = $command->getDisplay();

        // Assert display
        $this->assertSame(Command::SUCCESS, $command->getStatusCode());
        $this->assertStringContainsString("[OK]", $output);
        $this->assertStringContainsString("Gem drop probability was set to 0.2.", $output);

        // Assert values
        $this->assertSame(0.2, $scene->getProperty(Forest::GemDropProbabilitySceneProperty, null));
    }

    public function testIfGemDropProbabilityGetsResetAfterResettingItWithSceneConfigResetCommand()
    {
        $scene = $this->getForestScene();

        $command = new CommandTester(new SceneConfigResetCommand($this->g));
        $command->execute([
            "id" => $scene->getId(),
            "setting" => Forest::GemDropProbabilitySceneProperty,
        ]);
        $output = $command->getDisplay();

        // Assert display
        $this->assertSame(Command::SUCCESS, $command->getStatusCode());
        $this->assertStringContainsString("[OK]", $output);
        $this->assertStringContainsString("Gem drop probability was reset.", $output);

        // Assert values
        $this->assertNull($scene->getProperty(Forest::GemDropProbabilitySceneProperty));
    }

    public function testIfGemDropProbabilityCannotGetChangedAfterSettingItWithSceneConfigSetCommandToLowerThan0()
    {
        $scene = $this->getForestScene();

        $command = new CommandTester(new SceneConfigSetCommand($this->g));
        $command->execute([
            "id" => $scene->getId(),
            "setting" => Forest::GemDropProbabilitySceneProperty,
            "value" => -1,
        ]);
        $output = $command->getDisplay();

        // Assert display
        $this->assertSame(Command::SUCCESS, $command->getStatusCode());
        $this->assertStringContainsString("[ERROR]", $output);
        $this->assertStringContainsString("Gem drop probability cannot be smaller than 0.", $output);

        // Assert values
        $this->assertNull($scene->getProperty(Forest::GemDropProbabilitySceneProperty, null));
    }

    public function testIfGemDropProbabilityCannotGetChangedAfterSettingItWithSceneConfigSetCommandToBiggerThanOne()
    {
        $scene = $this->getForestScene();

        $command = new CommandTester(new SceneConfigSetCommand($this->g));
        $command->execute([
            "id" => $scene->getId(),
            "setting" => Forest::GemDropProbabilitySceneProperty,
            "value" => 2,
        ]);
        $output = $command->getDisplay();

        // Assert display
        $this->assertSame(Command::SUCCESS, $command->getStatusCode());
        $this->assertStringContainsString("[ERROR]", $output);
        $this->assertStringContainsString("Gem drop probability cannot be higher than 1.", $output);

        // Assert values
        $this->assertNull($scene->getProperty(Forest::GemDropProbabilitySceneProperty, null));
    }

    public function testIfLostExperienceUponDeathGetsChangedAfterSettingItWithSceneConfigSetCommand()
    {
        $scene = $this->getForestScene();

        $command = new CommandTester(new SceneConfigSetCommand($this->g));
        $command->execute([
            "id" => $scene->getId(),
            "setting" => Forest::LostExperienceUponDeathSceneProperty,
            "value" => 0.5,
        ]);
        $output = $command->getDisplay();

        // Assert display
        $this->assertSame(Command::SUCCESS, $command->getStatusCode());
        $this->assertStringContainsString("[OK]", $output);
        $this->assertStringContainsString("Lost experience factor was set to 0.5.", $output);

        // Assert values
        $this->assertSame(0.5, $scene->getProperty(Forest::LostExperienceUponDeathSceneProperty, null));
    }

    public function testIfLostExperienceUponDeathGetsResetAfterResettingItWithSceneConfigResetCommand()
    {
        $scene = $this->getForestScene();

        $command = new CommandTester(new SceneConfigResetCommand($this->g));
        $command->execute([
            "id" => $scene->getId(),
            "setting" => Forest::LostExperienceUponDeathSceneProperty,
        ]);
        $output = $command->getDisplay();

        // Assert display
        $this->assertSame(Command::SUCCESS, $command->getStatusCode());
        $this->assertStringContainsString("[OK]", $output);
        $this->assertStringContainsString("Lost experience factor was reset.", $output);

        // Assert values
        $this->assertNull($scene->getProperty(Forest::LostExperienceUponDeathSceneProperty, null));
    }

    public function testIfLostExperienceUponDeathCannotGetChangedAfterSettingItWithSceneConfigSetCommandToLowerThan0()
    {
        $scene = $this->getForestScene();

        $command = new CommandTester(new SceneConfigSetCommand($this->g));
        $command->execute([
            "id" => $scene->getId(),
            "setting" => Forest::LostExperienceUponDeathSceneProperty,
            "value" => -1,
        ]);
        $output = $command->getDisplay();

        // Assert display
        $this->assertSame(Command::SUCCESS, $command->getStatusCode());
        $this->assertStringContainsString("[ERROR]", $output);
        $this->assertStringContainsString("Lost experience fraction must be at least 0. ", $output);

        // Assert values
        $this->assertNull($scene->getProperty(Forest::LostExperienceUponDeathSceneProperty, null));
    }

    public function testIfLostExperienceUponDeathCannotGetChangedAfterSettingItWithSceneConfigSetCommandToBiggerThanOne()
    {
        $scene = $this->getForestScene();

        $command = new CommandTester(new SceneConfigSetCommand($this->g));
        $command->execute([
            "id" => $scene->getId(),
            "setting" => Forest::LostExperienceUponDeathSceneProperty,
            "value" => 2,
        ]);
        $output = $command->getDisplay();

        // Assert display
        $this->assertSame(Command::SUCCESS, $command->getStatusCode());
        $this->assertStringContainsString("[ERROR]", $output);
        $this->assertStringContainsString("Character cannot loose more experience than he has.", $output);

        // Assert values
        $this->assertNull($scene->getProperty(Forest::LostExperienceUponDeathSceneProperty, null));
    }
}