<?php
declare(strict_types=1);

namespace LotGD\Module\Forest\Scenes;

use Composer\Script\Event;
use LotGD\Core\Action;
use LotGD\Core\ActionGroup;
use LotGD\Core\Battle;
use LotGD\Core\Events\EventContext;
use LotGD\Core\Game;
use LotGD\Core\Models\Character;
use LotGD\Core\Models\FighterInterface;
use LotGD\Core\Models\Scene;
use LotGD\Core\Models\SceneConnectionGroup;
use LotGD\Core\Models\Viewpoint;
use LotGD\Module\Res\Fight\Fight;

use LotGD\Module\Forest\Managers\CreatureManager;
use LotGD\Module\Forest\Models\Creature;
use LotGD\Module\Forest\Module as ForestModule;

/**
 * Class Forest, contains helper methods for forest events
 * @package LotGD\Module\Forest\Scene
 */
class Forest
{
    const Template = "lotgd/module-forest/forest";
    const Groups = [
        "healing" => ["lotgd/module-forest/forest/healing", "Healing"],
        "fight" => ["lotgd/module-forest/forest/fight", "Fight"],
        "back" => ["lotgd/module-forest/forest/back", "Back"],
    ];

    public static function create(): array
    {
        $forestScene = Scene::create([
            "template" => self::Template,
            "title" => "The Forest",
            "description" => "The Forest, home to evil creatures and evildoers of all sorts.
            
    The thick foliage of the forest restricts your view to only a few yards in most places.
    The paths would be imperceptible except for your trained eye.
    You move silently as a soft breeze across the thick moss covering the ground, wary to
    avoid stepping on a twig or any of the numerous pieces of bleached bone that populate
    the forest floor, lest you betray your presence to one of the vile beasts that wander
    the forest.",
            ]
        );

        foreach (self::Groups as $key => $val) {
            $forestScene->addConnectionGroup(new SceneConnectionGroup($val[0], $val[1]));
        }

        $healerScene = Healer::create();

        $forestScene
            ->getConnectionGroup(self::Groups["healing"][0])
            ->connect($healerScene->getConnectionGroup(Healer::Groups["back"][0]));

        return [$forestScene, $healerScene];
    }

    public static function handleEvent(Game $g, EventContext $context): EventContext
    {
        /** @var array $parameters */
        $parameters = $context->getDataField("parameters");

        if (isset($parameters["search"])) {
            return self::handleFightSearch($g, $context);
        } elseif (isset($parameters[Fight::ActionParameterField])) {
            return self::handleFightRound($g, $context);
        } else {
            return self::handleMainForest($g, $context);
        }

        // Do nothing
        return $context;
    }

    public static function handleMainForest(Game $g, EventContext $context): EventContext
    {
        /** @var Viewpoint $v */
        $v = $context->getDataField("viewpoint");
        /** @var Character $c */
        $c = $g->getCharacter();

        $forestid = $v->getScene()->getId();

        // Add an action for fighting - if enough healthpoints

        if ($c->isAlive()) {
            $fightAction = [new Action($forestid, "Search for a fight", ["search" => CreatureManager::FightDifficultyNormal])];

            if ($c->getLevel() > 1) {
                $fightAction[] = new Action($forestid, "Go Slumming", ["search" => CreatureManager::FightDifficultyEasy]);
            }
            $fightAction[] = new Action($forestid, "Go Thrillseeking", ["search" => CreatureManager::FightDifficultyHard]);

            if ($v->hasActionGroup(self::Groups["fight"][0])) {
                foreach ($fightActions as $action) {
                    $v->addActionToGroupId($fightAction, self::Groups["fight"][0]);
                }
            } else {
                $group = new ActionGroup(self::Groups["fight"][0], self::Groups["fight"][1], 0);
                $group->setActions($fightAction);
                $v->addActionGroup($group);
            }
        }

        return $context;
    }

    public static function handleFightSearch(Game $g, EventContext $context): EventContext
    {
        /** @var Viewpoint $v */
        $v = $context->getDataField("viewpoint");
        /** @var Character $c */
        $c = $g->getCharacter();

        // @ToDo: Implement hooks for events

        // Get a (detached) creature in order to start a fight.
        $creatureManager = new CreatureManager($g);
        $creature = $creatureManager->getCreature($c->getLevel(), $context->getDataField("parameters")["search"]);

        // @ToDo: Allow normal fight, monster is surprised and player is surprised.
        $v->setTitle("A fight!");

        switch($context->getDataField("parameters")["search"]) {
            case CreatureManager::FightDifficultyEasy:
                $v->setDescription(sprintf(
                        "You head for the section of forest you know to contain foes that you're a bit more 
                        comfortable with. You encounter a %s that attacks you with its weapon %s.",
                        $creature->getDisplayName(), $creature->getWeapon($g))
                );
                break;

            case CreatureManager::FightDifficultyHard:
                $v->setDescription(sprintf(
                        "You head for the section of forest which contains creatures of your nightmares, hoping 
                        to find one of them injured. You encounter a %s that attacks you with its weapon %s.",
                        $creature->getDisplayName(), $creature->getWeapon($g))
                );
                break;

            case CreatureManager::FightDifficultyNormal:
                $v->setDescription(sprintf(
                    "You are strolling through the forest, trying to find a creature to kill. You encounter a 
                        %s that attacks you with its weapon %s.",
                    $creature->getDisplayName(), $creature->getWeapon($g))
                );
                break;
        }

        $battle = new Battle($g, $c, $creature);

        // Good idea would be to pass a "return possibility" to getFightAction - this way, it would return that if fight is over... Alternatively, we check it ourselves?
        $battleActionGroups = Fight::getFightActions($v->getScene(), $battle);

        // @ToDo: Implement API changes to allow something like $v->cleanActions(); and to check whether if after saving the viewpoint any action is left.
        $v->setActionGroups($battleActionGroups);

        // Store battle
        $c->setProperty(ForestModule::CharacterPropertyBattleState, $battle->serialize());

        return $context;
    }

    public static function handleFightRound(Game $g, EventContext $context): EventContext
    {
        /** @var Viewpoint $v */
        $v = $context->getDataField("viewpoint");
        /** @var Character $c */
        $c = $g->getCharacter();
        /** @var array $parameters */
        $parameters = $context->getDataField("parameters");

        // Restore battle
        $battle = Battle::unserialize($g, $c, $c->getProperty(ForestModule::CharacterPropertyBattleState));

        // Battle
        Fight::processBattleOption($g, $battle, $v, $parameters);

        // Decide on actions
        if ($battle->isOver()) {
            // Forest actions
            // @ToDo: Experience calculation
            if ($battle->getWinner() === $c) {
                $v->setTitle("You won!");

                $v->addDescriptionParagraph(sprintf("You defeated %s. You gain no experience.", $battle->getLoser()->getDisplayName()));
            } else {
                $v->setTitle("You died!");

                $v->addDescriptionParagraph(sprintf("You haven defeated by %s. They stand over your dead body, laughting..", $battle->getWinner()->getDisplayName()));
            }

            $c->setProperty(ForestModule::CharacterPropertyBattleState, null);
        } else {
            // Fight actions
            $v->setActionGroups(Fight::getFightActions($v->getScene(), $battle));

            $c->setProperty(ForestModule::CharacterPropertyBattleState, $battle->serialize());
        }

        return $context;
    }
}