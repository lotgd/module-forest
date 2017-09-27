<?php
declare(strict_types=1);

namespace LotGD\Module\Forest\Tests;

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
        $context = new \LotGD\Core\Events\EventContext(
            "e/lotgd/tests/unknown-event",
            "none",
            \LotGD\Core\Events\EventContextData::create([])
        );

        Module::handleEvent($this->g, $context);
    }

    public function testModuleFlow()
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
        $this->assertContains("You feel energized!", $descriptions);

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

        // Attack
        do {
            $game->takeAction($action->getId());

            if ($character->getProperty(Module::CharacterPropertyBattleState) !== null){
                $action = $this->assertHasAction($v, ["getTitle", "Attack"], "Fight");
            } else {
                break;
            }
        } while (true);

        $this->assertSame("You won!", $v->getTitle());

        // No go to healing.
        $action = $this->assertHasAction($v, ["getDestinationSceneId", 6], "Healing");
        $game->takeAction($action->getId());
        $this->assertSame("Healer's Hut", $v->getTitle());
        $action = $this->assertHasAction($v, ["getDestinationSceneId", 6], "Heal");
    }
}
