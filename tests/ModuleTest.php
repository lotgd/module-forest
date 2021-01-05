<?php
declare(strict_types=1);

namespace LotGD\Module\Forest\Tests;

use LotGD\Core\Events\EventContext;
use LotGD\Core\Events\EventContextData;
use LotGD\Core\Game;
use LotGD\Core\Models\Character;
use LotGD\Module\Forest\SceneTemplates\Fight;
use LotGD\Module\Res\Fight\Tests\helpers\EventRegistry;
use LotGD\Module\Res\Fight\Module as ResFightModule;

use LotGD\Module\Forest\Module;

class ModuleTest extends ModuleTestCase
{
    const Library = 'lotgd/module-forest';

    protected function getDataSet(): \PHPUnit_Extensions_Database_DataSet_YamlDataSet
    {
        return new \PHPUnit_Extensions_Database_DataSet_YamlDataSet(implode(DIRECTORY_SEPARATOR, [__DIR__, 'datasets', 'module.yml']));
    }

    public function testHandleUnknownEvent()
    {
        // Always good to test a non-existing event just to make sure nothing happens :).
        $context = new EventContext(
            "e/lotgd/tests/unknown-event",
            "none",
            EventContextData::create([])
        );

        Module::handleEvent($this->g, $context);
    }

    public function getTestSceneIds()
    {
        $em = $this->getEntityManager();
        $game = $this->g; /* @var Game $game */

        $module = $game->getModuleManager()->getModule(Module::Module);
        $scenes = $module->getProperty(Module::GeneratedSceneProperty);
        return [$scenes["forest"][0], $scenes["healer"][0]];
    }

    public function testModuleFlowWhileCharacterStaysAlive()
    {
        /** @var Game $game */
        $game = $this->g;
        /** @var Character $character */
        $character = $this->getEntityManager()->getRepository(Character::class)->findById("10000000-0000-0000-0000-000000000001")[0];
        $game->setCharacter($character);
        $v = $game->getViewpoint();

        [$forestSceneId, $healerSceneId] = $this->getTestSceneIds();

        // Assert new day happened
        $this->assertSame("It is a new day!", $v->getTitle());

        // Assert that our new day inserts work
        $descriptions = explode("\n\n", $v->getDescription());
        $this->assertContains("You feel energized! Today, you can fight for 20 rounds.", $descriptions);
        $this->assertSame($character->getMaxHealth(), $character->getHealth());
        $this->assertSame(20, $character->getTurns());
        $character->setHealth(90);

        // Should be in the village
        $action = $v->getActionGroups()[0]->getActions()[0];
        $game->takeAction($action->getId());
        $this->assertSame("Village", $v->getTitle());
        $action = $this->assertHasAction($v, ["getDestinationSceneId", $forestSceneId], "Outside");

        // Go to the forest
        $game->takeAction($action->getId());
        $this->assertSame("The Forest", $v->getTitle());
        $action = $this->assertHasAction($v, ["getDestinationSceneId", $healerSceneId], "Healing");
        $this->assertHasAction($v, ["getTitle", "Search for a fight"], "Fight");
        $this->assertHasAction($v, ["getTitle", "Go Thrillseeking"], "Fight");
        $this->assertHasAction($v, ["getTitle", "Go Slumming"], "Fight");

        // Go to the healer.
        $game->takeAction($action->getId());
        $this->assertSame("Healer's Hut", $v->getTitle());
        $action = $this->assertHasAction($v, ["getDestinationSceneId", $forestSceneId], "Back");

        // Back to the forest
        $game->takeAction($action->getId());
        $this->assertSame("The Forest", $v->getTitle());
        $action = $this->assertHasAction($v, ["getTitle", "Search for a fight"], "Fight");

        // Start a fight.
        $game->takeAction($action->getId());
        $action = $this->assertHasAction($v, ["getTitle", "Attack"], "Fight");

        // Save experience first.
        $currentExp = $character->getProperty(ResFightModule::CharacterPropertyCurrentExperience, 0);

        // Attack until someone dies.
        do {
            $game->takeAction($action->getId());

            if ($character->getProperty(ResFightModule::CharacterPropertyBattleState) !== null){
                $action = $this->assertHasAction($v, ["getTitle", "Attack"], "Fight");
            } else {
                break;
            }
        } while (true);

        $this->assertSame("You won!", $v->getTitle());
        $this->assertGreaterThan($currentExp, $character->getProperty(ResFightModule::CharacterPropertyCurrentExperience, 0));

        // Now go to healing.
        $action = $this->assertHasAction($v, ["getDestinationSceneId", $healerSceneId], "Healing");
        $game->takeAction($action->getId());
        $this->assertSame("Healer's Hut", $v->getTitle());

        // Assert that we are not completely healed.
        $this->assertLessThan($character->getMaxHealth(), $character->getHealth());
        $action = $this->assertHasAction($v, ["getTitle", "Complete Healing"], "Potions");
        $game->takeAction($action->getId());
        // Assert we are.
        $this->assertEquals($character->getMaxHealth(), $character->getHealth());
    }

    public function testIfHealingOptionsAreOnlyVisibleToDamagedCharacters()
    {
        /** @var Game $game */
        $game = $this->g;
        /** @var Character $character */
        $character = $this->getEntityManager()->getRepository(Character::class)->find("10000000-0000-0000-0000-000000000002");
        $character->setProperty(\LotGD\Module\NewDay\Module::CharacterPropertyLastNewDay, new \DateTime());
        $game->setCharacter($character);
        $v = $game->getViewpoint();

        [$forestSceneId, $healerSceneId] = $this->getTestSceneIds();

        // Take actions
        $this->takeActions($game, $v, [$forestSceneId, $healerSceneId]);
        $this->assertHasAction($v, ["getTitle", "Complete Healing"], "Potions");

        // Heal, go back and return
        $character->setHealth($character->getMaxHealth());
        $this->takeActions($game, $v, [$forestSceneId, $healerSceneId]);
        $this->assertNotHasAction($v, ["getTitle", "Complete Healing"], "Potions");
    }

    public function testIfHealerSuccessfullyRemovesHealthAboveMaximum()
    {
        /** @var Game $game */
        $game = $this->g;
        /** @var Character $character */
        $character = $this->getEntityManager()->getRepository(Character::class)->find("10000000-0000-0000-0000-000000000003");
        $character->setProperty(\LotGD\Module\NewDay\Module::CharacterPropertyLastNewDay, new \DateTime());
        $game->setCharacter($character);
        $v = $game->getViewpoint();

        [$forestSceneId, $healerSceneId] = $this->getTestSceneIds();

        // Take actions
        $this->assertGreaterThan($character->getMaxHealth(), $character->getHealth());
        $this->takeActions($game, $v, [$forestSceneId, $healerSceneId]);
        $this->assertNotHasAction($v, ["getTitle", "Complete Healing"], "Potions");
        $this->assertSame($character->getMaxHealth(), $character->getHealth());
    }

    public function testIfDeadPeopleCannotFightOrHeal()
    {
        /** @var Game $game */
        $game = $this->g;
        /** @var Character $character */
        $character = $this->getEntityManager()->getRepository(Character::class)->find("10000000-0000-0000-0000-000000000004");
        $character->setProperty(\LotGD\Module\NewDay\Module::CharacterPropertyLastNewDay, new \DateTime());
        $game->setCharacter($character);
        $v = $game->getViewpoint();

        // Take actions
        $this->assertSame(0, $character->getHealth());
        $this->takeActions($game, $v, ["20000000-0000-0000-0000-000000000005"]);
        $this->assertNotHasAction($v, ["getTitle", "Search for a fight"], "Fight");
        $this->takeActions($game, $v, ["20000000-0000-0000-0000-000000000006"]);
        $this->assertNotHasAction($v, ["getTitle", "Complete Healing"], "Potions");
    }

    public function testIfTiredCharacterCannotStartAFight()
    {
        /** @var Game $game */
        $game = $this->g;
        /** @var Character $character */
        $character = $this->getEntityManager()->getRepository(Character::class)->find("10000000-0000-0000-0000-000000000006");
        $character->setProperty(\LotGD\Module\NewDay\Module::CharacterPropertyLastNewDay, new \DateTime());
        $game->setCharacter($character);
        $v = $game->getViewpoint();

        // Take actions
        $character->setTurns(0);
        $this->takeActions($game, $v, [5]);
        $this->assertNotHasAction($v, ["getTitle", "Search for a fight"], "Fight");
    }

    public function testIfAForestFightEndsProperlyIfTheCharacterDied()
    {
        /** @var Game $game */
        $game = $this->g;
        /** @var Character $character */
        $character = $this->getEntityManager()->getRepository(Character::class)->find("10000000-0000-0000-0000-000000000005");
        $character->setProperty(\LotGD\Module\NewDay\Module::CharacterPropertyLastNewDay, new \DateTime());
        $game->setCharacter($character);
        $v = $game->getViewpoint();


        [$forestSceneId, $healerSceneId] = $this->getTestSceneIds();

        // Take actions
        $this->assertSame(1, $character->getHealth());
        $this->takeActions($game, $v, [$forestSceneId, "Go Thrillseeking"]);
        $action = $this->assertHasAction($v, ["getTitle", "Attack"], "Fight");

        // Attack until someone dies.
        // Make sure we die.
        $character->setLevel(1);
        do {
            $game->takeAction($action->getId());

            if ($character->getProperty(ResFightModule::CharacterPropertyBattleState) !== null){
                $action = $this->assertHasAction($v, ["getTitle", "Attack"], "Fight");
            } else {
                break;
            }
        } while (true);

        $this->assertSame("You died!", $v->getTitle());
        $this->assertNotHasAction($v, ["getTitle", "Search for a fight"], "Fight");
        $this->assertNotHasAction($v, ["getTitle", "Go Thrillseeking"], "Fight");
        $this->assertNotHasAction($v, ["getTitle", "Go Slumming"], "Fight");
        $this->assertHasAction($v, ["getDestinationSceneId", "20000000-0000-0000-0000-000000000001"], "Back");
    }
}
