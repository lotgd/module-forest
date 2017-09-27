<?php
declare(strict_types=1);

namespace LotGD\Module\Forest\Scenes;

use Doctrine\DBAL\Schema\View;
use LotGD\Core\Action;
use LotGD\Core\ActionGroup;
use LotGD\Core\Battle;
use LotGD\Core\Game;
use LotGD\Core\Models\Scene;
use LotGD\Core\Models\Viewpoint;
use LotGD\Module\Forest\Module as ForestModule;

class Fight
{
    const ActionGroupFight = ForestModule::ModuleIdentifier . "/fight";
    const ActionGroupFlee = ForestModule::ModuleIdentifier . "/flee";

    const ActionGroupDetails = [
        self::ActionGroupFight => []
    ];

    const ActionParameterField = ForestModule::ModuleIdentifier . "/inFight";
    const ActionParameterAttack = ForestModule::ModuleIdentifier . "/attack";

    public static function getFightActions(Scene $scene, Battle $battle): ?array
    {
        if ($battle->isOver()) {
            return null;
        }

        /** @var $groups<ActionGroup> */
        $groups = [
            new ActionGroup(self::ActionGroupFight, "Fight", 0),
            new ActionGroup(self::ActionGroupFlee, "Flee", 100)
        ];

        $groups[0]->setActions([
            new Action($scene->getId(), "Attack", [self::ActionParameterField => self::ActionParameterAttack])
        ]);

        return $groups;
    }

    public static function processBattleOption(Game $g, Battle $battle, Viewpoint $v, array $parameters)
    {
        $v->setTitle("A fight!");
        $v->clearDescription("");

        if (isset($parameters[self::ActionParameterField])) {
            switch($parameters[self::ActionParameterField]) {
                default:
                case "attack":
                    $battle->fightNRounds();
                    break;
            }

            $events = $battle->getEvents();

            foreach ($events as $event) {
                $v->addDescriptionParagraph($event->decorate($g));
            }
        }
    }
}