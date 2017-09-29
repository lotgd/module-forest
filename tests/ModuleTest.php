<?php
declare(strict_types=1);

namespace LotGD\Module\Forest\Tests;

use LotGD\Core\Events\EventContext;
use LotGD\Core\Events\EventContextData;
use LotGD\Core\Models\Scene;
use LotGD\Module\Forest\Scenes\Fight;
use Monolog\Logger;
use Monolog\Handler\NullHandler;

use LotGD\Core\Configuration;
use LotGD\Core\Game;
use LotGD\Core\Models\Character;
use LotGD\Core\Models\Module as ModuleModel;

use LotGD\Module\Forest\Module;

class ModuleTest extends ModuleTestCase
{
    const Library = 'lotgd/module-project';

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

    public function testModuleFlowWhileCharacterStaysAlive()
    {
        /** @var Game $game */
        $game = $this->g;
        /** @var Character $character */
        $character = $this->getEntityManager()->getRepository(Character::class)->findById(1)[0];
        $game->setCharacter($character);
        $v = $game->getViewpoint();

        // Assert new day happened
        $this->assertSame("It is a new day!", $v->getTitle());

        // Assert that our new day inserts work
        $descriptions = explode("\n\n", $v->getDescription());
        $this->assertContains("You feel energized! Today, you can fight for 20 rounds.", $descriptions);
        $this->assertSame($character->getMaxHealth(), $character->getHealth());
        $character->setHealth(90);

        // Should be in the village
        $action = $v->getActionGroups()[0]->getActions()[0];
        $game->takeAction($action->getId());
        $this->assertSame("Village", $v->getTitle());
        $action = $this->assertHasAction($v, ["getDestinationSceneId", 5], "Outside");

        // Go to the forest
        $game->takeAction($action->getId());
        $this->assertSame("The Forest", $v->getTitle());
        $action = $this->assertHasAction($v, ["getDestinationSceneId", 6], "Healing");
        $this->assertHasAction($v, ["getTitle", "Search for a fight"], "Fight");
        $this->assertHasAction($v, ["getTitle", "Go Thrillseeking"], "Fight");
        $this->assertHasAction($v, ["getTitle", "Go Slumming"], "Fight");

        // Go to the healer.
        $game->takeAction($action->getId());
        $this->assertSame("Healer's Hut", $v->getTitle());
        $action = $this->assertHasAction($v, ["getDestinationSceneId", 5], "Back");

        // Back to the forest
        $game->takeAction($action->getId());
        $this->assertSame("The Forest", $v->getTitle());
        $action = $this->assertHasAction($v, ["getTitle", "Search for a fight"], "Fight");

        // Start a fight.
        $game->takeAction($action->getId());
        $action = $this->assertHasAction($v, ["getTitle", "Attack"], "Fight");

        // Attack until someone dies.
        do {
            $game->takeAction($action->getId());

            if ($character->getProperty(Module::CharacterPropertyBattleState) !== null){
                $action = $this->assertHasAction($v, ["getTitle", "Attack"], "Fight");
            } else {
                break;
            }
        } while (true);

        $this->assertSame("You won!", $v->getTitle());

        // Now go to healing.
        $action = $this->assertHasAction($v, ["getDestinationSceneId", 6], "Healing");
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
        $character = $this->getEntityManager()->getRepository(Character::class)->find(2);
        $character->setProperty(\LotGD\Module\NewDay\Module::CharacterPropertyLastNewDay, new \DateTime());
        $game->setCharacter($character);
        $v = $game->getViewpoint();

        // Take actions
        $this->takeActions($game, $v, [5, 6]);
        $this->assertHasAction($v, ["getTitle", "Complete Healing"], "Potions");

        // Heal, go back and return
        $character->setHealth($character->getMaxHealth());
        $this->takeActions($game, $v, [5, 6]);
        $this->assertNotHasAction($v, ["getTitle", "Complete Healing"], "Potions");
    }

    public function testIfHealerSuccessfullyRemovesHealthAboveMaximum()
    {
        /** @var Game $game */
        $game = $this->g;
        /** @var Character $character */
        $character = $this->getEntityManager()->getRepository(Character::class)->find(3);
        $character->setProperty(\LotGD\Module\NewDay\Module::CharacterPropertyLastNewDay, new \DateTime());
        $game->setCharacter($character);
        $v = $game->getViewpoint();

        // Take actions
        $this->assertGreaterThan($character->getMaxHealth(), $character->getHealth());
        $this->takeActions($game, $v, [5, 6]);
        $this->assertNotHasAction($v, ["getTitle", "Complete Healing"], "Potions");
        $this->assertSame($character->getMaxHealth(), $character->getHealth());
    }

    public function testIfDeadPeopleCannotFightOrHeal()
    {
        /** @var Game $game */
        $game = $this->g;
        /** @var Character $character */
        $character = $this->getEntityManager()->getRepository(Character::class)->find(4);
        $character->setProperty(\LotGD\Module\NewDay\Module::CharacterPropertyLastNewDay, new \DateTime());
        $game->setCharacter($character);
        $v = $game->getViewpoint();

        // Take actions
        $this->assertSame(0, $character->getHealth());
        $this->takeActions($game, $v, [5]);
        $this->assertNotHasAction($v, ["getTitle", "Search for a fight"], "Fight");
        $this->takeActions($game, $v, [6]);
        $this->assertNotHasAction($v, ["getTitle", "Complete Healing"], "Potions");
    }

    public function testIfAForestFightEndsProperlyIfTheCharacterDied()
    {
        /** @var Game $game */
        $game = $this->g;
        /** @var Character $character */
        $character = $this->getEntityManager()->getRepository(Character::class)->find(5);
        $character->setProperty(\LotGD\Module\NewDay\Module::CharacterPropertyLastNewDay, new \DateTime());
        $game->setCharacter($character);
        $v = $game->getViewpoint();

        // Take actions
        $this->assertSame(1, $character->getHealth());
        $this->takeActions($game, $v, [5, "Go Thrillseeking"]);
        $action = $this->assertHasAction($v, ["getTitle", "Attack"], "Fight");

        // Attack until someone dies.
        // Make sure we die.
        $character->setLevel(1);
        do {
            $game->takeAction($action->getId());

            if ($character->getProperty(Module::CharacterPropertyBattleState) !== null){
                $action = $this->assertHasAction($v, ["getTitle", "Attack"], "Fight");
            } else {
                break;
            }
        } while (true);

        $this->assertSame("You died!", $v->getTitle());
        $this->assertNotHasAction($v, ["getTitle", "Search for a fight"], "Fight");
        $this->assertNotHasAction($v, ["getTitle", "Go Thrillseeking"], "Fight");
        $this->assertNotHasAction($v, ["getTitle", "Go Slumming"], "Fight");
        $this->assertHasAction($v, ["getDestinationSceneId", 1], "Back");
    }
}
