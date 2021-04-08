<?php
declare(strict_types=1);

namespace LotGD\Module\Forest\Tests;

use LotGD\Core\Events\EventContext;
use LotGD\Core\Events\EventContextData;
use LotGD\Core\Game;
use LotGD\Core\Models\Character;
use LotGD\Core\Models\Scene;
use LotGD\Module\Forest\SceneTemplates\Fight;
use LotGD\Module\Forest\SceneTemplates\Healer;
use LotGD\Module\Res\Fight\Tests\helpers\EventRegistry;
use LotGD\Module\Res\Fight\Module as ResFightModule;

use LotGD\Module\Forest\Module;

class ModuleTest extends ModuleTestCase
{
    const Library = 'lotgd/module-forest';

    public function useSilentHandler(): bool
    {
        return true;
    }

    /**
     * @doesNotPerformAssertions
     */
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

        $this->assertHasAction($v, ["getDestinationSceneId", $forestSceneId], "Outside");
        $action = $this->getAction($v, ["getDestinationSceneId", $forestSceneId], "Outside");

        // Go to the forest
        $game->takeAction($action->getId());
        $this->assertSame("The Forest", $v->getTitle());
        $this->assertHasAction($v, ["getDestinationSceneId", $healerSceneId], "Healing");
        $action = $this->getAction($v, ["getDestinationSceneId", $healerSceneId], "Healing");
        $this->assertHasAction($v, ["getTitle", "Search for a fight"], "Fight");
        $this->assertHasAction($v, ["getTitle", "Go Thrillseeking"], "Fight");
        $this->assertHasAction($v, ["getTitle", "Go Slumming"], "Fight");

        // Go to the healer.
        $game->takeAction($action->getId());
        $this->assertSame("Healer's Hut", $v->getTitle());
        $this->assertHasAction($v, ["getDestinationSceneId", $forestSceneId], "Back");
        $action = $this->getAction($v, ["getDestinationSceneId", $forestSceneId], "Back");

        // Back to the forest
        $game->takeAction($action->getId());
        $this->assertSame("The Forest", $v->getTitle());
        $this->assertHasAction($v, ["getTitle", "Search for a fight"], "Fight");
        $action = $this->getAction($v, ["getTitle", "Search for a fight"], "Fight");

        // Start a fight.
        $game->takeAction($action->getId());
        $this->assertHasAction($v, ["getTitle", "Attack"], "Fight");
        $action = $this->getAction($v, ["getTitle", "Attack"], "Fight");

        // Save experience first.
        $currentExp = $character->getProperty(ResFightModule::CharacterPropertyCurrentExperience, 0);

        // Attack until someone dies.
        do {
            $game->takeAction($action->getId());

            if ($character->getProperty(ResFightModule::CharacterPropertyBattleState) !== null){
                $this->assertHasAction($v, ["getTitle", "Attack"], "Fight");
                $action = $this->getAction($v, ["getTitle", "Attack"], "Fight");
            } else {
                break;
            }
        } while (true);

        $this->assertSame("You won!", $v->getTitle());
        $this->assertGreaterThan($currentExp, $character->getProperty(ResFightModule::CharacterPropertyCurrentExperience, 0));

        // Now go to healing.
        $this->assertHasAction($v, ["getDestinationSceneId", $healerSceneId], "Healing");
        $action = $this->getAction($v, ["getDestinationSceneId", $healerSceneId], "Healing");
        $game->takeAction($action->getId());
        $this->assertSame("Healer's Hut", $v->getTitle());

        // Assert that we are not completely healed.
        $this->assertLessThan($character->getMaxHealth(), $character->getHealth());

        $actionGroup = $v->findActionGroupById(Healer::Groups["healing"][0]);
        $this->assertCount(10, $actionGroup->getActions());
        $action = $actionGroup->getActions()[0];
        $this->assertTrue(str_starts_with($action->getRenderedTitle(), "Complete Healing"));

        // Make sure the test character has enough gold!
        $character->setGold(20000);

        $game->takeAction($action->getId());

        $this->assertTrue(str_starts_with($v->getRenderedDescription(), "With a grimace"));
        $this->assertTrue(str_contains($v->getRenderedDescription(), "You have been healed"));
        $this->assertTrue(str_ends_with($v->getRenderedDescription(), "points!"));


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

        // Assert if we have the correct scene
        $this->assertSame($healerSceneId, $v->getScene()->getId());
        // Assert if we find healing options
        $actionGroup = $v->findActionGroupById(Healer::Groups["healing"][0]);
        $this->assertCount(10, $actionGroup->getActions());
        $action = $actionGroup->getActions()[0];
        $this->assertTrue(str_starts_with($action->getRenderedTitle(), "Complete Healing"));

        // Heal, go back and return
        $character->setHealth($character->getMaxHealth());
        $this->takeActions($game, $v, [$forestSceneId, $healerSceneId]);
        // Assert if we have the correct scene
        $this->assertSame($healerSceneId, $v->getScene()->getId());
        // Assert that we have no healing options
        $actionGroup = $v->findActionGroupById(Healer::Groups["healing"][0]);
        $this->assertNull($actionGroup);
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

        [$forestSceneId, $healerSceneId] = $this->getTestSceneIds();

        // Take actions
        $this->assertSame(0, $character->getHealth());
        $this->takeActions($game, $v, [$forestSceneId]);
        $this->assertNotHasAction($v, ["getTitle", "Search for a fight"], "Fight");
        $this->takeActions($game, $v, [$healerSceneId]);
        $this->assertNotHasAction($v, ["getTitle", "Complete Healing"], "Potions");
        $this->assertStringContainsString("Slain you were. Doing for the dead nothing I can. Leaving you must.", $v->getDescription());
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
        $this->assertHasAction($v, ["getTitle", "Attack"], "Fight");
        $action = $this->getAction($v, ["getTitle", "Attack"], "Fight");

        // Attack until someone dies.
        // Make sure we die.
        $character->setLevel(1);
        do {
            $game->takeAction($action->getId());

            if ($character->getProperty(ResFightModule::CharacterPropertyBattleState) !== null){
                $this->assertHasAction($v, ["getTitle", "Attack"], "Fight");
                $action = $this->getAction($v, ["getTitle", "Attack"], "Fight");
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
