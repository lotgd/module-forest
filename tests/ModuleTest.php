<?php
declare(strict_types=1);

namespace LotGD\Module\Forest\Tests;

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

        // Change viewpoint by taking an action - assert restored scene.
        $action = $v->getActionGroups()[0]->getActions()[0];
        $game->takeAction($action->getId());
        $this->assertSame("Village", $v->getTitle());

        // Check if village is connected to forest
        $groups = $v->getActionGroups();
        $found = false;
        foreach ($groups as $group) {
            if ($group->getTitle() == "Outside") {
                $actions = $group->getActions();
                foreach ($actions as $action) {
                    if ($action->getDestinationSceneId() == 5) {
                        $found = $action->getId();
                    }
                }
            }
        }
        $this->assertNotFalse($found);

        // Go to the forest
        $game->takeAction($action->getId());
        $this->assertSame("The Forest", $v->getTitle());
    }
}
