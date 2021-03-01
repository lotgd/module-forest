<?php
declare(strict_types=1);

namespace LotGD\Module\Forest\SceneTemplates;

use LotGD\Core\Action;
use LotGD\Core\ActionGroup;
use LotGD\Core\Battle;
use LotGD\Core\Events\EventContext;
use LotGD\Core\Events\ViewpointDecorationEventData;
use LotGD\Core\Game;
use LotGD\Core\Models\Character;
use LotGD\Core\Models\Scene;
use LotGD\Core\Models\SceneConnectable;
use LotGD\Core\Models\SceneConnection;
use LotGD\Core\Models\SceneConnectionGroup;
use LotGD\Core\Models\SceneProperty;
use LotGD\Core\Models\SceneTemplate;
use LotGD\Core\Models\Viewpoint;
use LotGD\Core\SceneTemplates\SceneTemplateInterface;
use LotGD\Module\Res\Fight\Fight;

use LotGD\Module\Forest\Managers\CreatureManager;
use LotGD\Module\Forest\Models\Creature;
use LotGD\Module\Forest\Module;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Class Forest, contains helper methods for forest events
 * @package LotGD\Module\Forest\Scene
 */
class Forest implements SceneTemplateInterface
{
    const Template = "lotgd/module-forest/forest";
    const Groups = [
        "healing" => ["lotgd/module-forest/forest/healing", "Healing"],
        "fight" => ["lotgd/module-forest/forest/fight", "Fight"],
        "back" => ["lotgd/module-forest/forest/back", "Back"],
    ];

    // Scene properties
    const GemDropProbabilitySceneProperty = Module::Module . "/gemDropProbability";
    const LostExperienceUponDeathSceneProperty = Module::Module . "/lostExperienceUponDeath";

    private static ?SceneTemplate $template = null;

    public static function getNavigationEvent(): string
    {
        return self::Template;
    }

    /**
     * Creates the scene template
     * @return array
     */
    public static function create(): array
    {
        if (self::$template === null) {
            self::$template = new SceneTemplate(self::class, Module::Module);
        }

        $forestScene = new Scene(
            title: "The Forest",
            description: <<<TXT
                The Forest, home to evil creatures and evildoers of all sorts.
                        
                The thick foliage of the forest restricts your view to only a few yards in most places.
                The paths would be imperceptible except for your trained eye.
                You move silently as a soft breeze across the thick moss covering the ground, wary to
                avoid stepping on a twig or any of the numerous pieces of bleached bone that populate
                the forest floor, lest you betray your presence to one of the vile beasts that wander
                the forest."
            TXT,
            template: self::$template,
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

    /**
     * Handles the navigation-to forest event
     * @param Game $g
     * @param EventContext $context
     * @return EventContext
     */
    public static function handleEvent(Game $g, EventContext $context): EventContext
    {
        /** @var array $parameters */
        $parameters = $context->getDataField("parameters");
        $viewpoint = $context->getDataField("viewpoint");

        if (isset($parameters["search"])) {
            return self::handleFightSearch($g, $context);
        } else {
            return self::handleMainForest($g, $context, $viewpoint->getScene()->getId());
        }
    }

    /**
     * Adds additional forest actions, such as options to search for a fight.
     * @param Game $g
     * @param EventContext $context
     * @param int $forestid
     * @return EventContext
     */
    public static function handleMainForest(Game $g, EventContext $context, string $forestid): EventContext
    {
        /** @var Viewpoint $v */
        $v = $context->getDataField("viewpoint");
        /** @var Character $c */
        $c = $g->getCharacter();

        // Add an action for fighting - if enough healthpoints

        if ($c->isAlive() and $c->getTurns() > 0) {
            $fightAction = [new Action($forestid, "Search for a fight", ["search" => CreatureManager::FightDifficultyNormal])];

            if ($c->getLevel() > 1) {
                $fightAction[] = new Action($forestid, "Go Slumming", ["search" => CreatureManager::FightDifficultyEasy]);
            }
            $fightAction[] = new Action($forestid, "Go Thrillseeking", ["search" => CreatureManager::FightDifficultyHard]);

            $actionGroup = $v->findActionGroupById(self::Groups["fight"][0]);
            if ($actionGroup) {
                foreach ($fightAction as $action) {
                    $v->addActionToGroupId($action, self::Groups["fight"][0]);
                }
            } else {
                $group = new ActionGroup(self::Groups["fight"][0], self::Groups["fight"][1], 0);
                $group->setActions($fightAction);
                $v->addActionGroup($group);
            }
        }

        $hookData = $g->getEventManager()->publish(
            Module::HookForestNavigation,
            ViewpointDecorationEventData::create(["viewpoint" => $v])
        );

        return $context;
    }

    /**
     * Handles the navigation
     * @param Game $g
     * @param EventContext $context
     * @return EventContext
     */
    public static function handleFightSearch(Game $g, EventContext $context): EventContext
    {
        /** @var Viewpoint $v */
        $v = $context->getDataField("viewpoint");
        /** @var Character $c */
        $c = $g->getCharacter();

        $c->incrementTurns(-1);

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

        $fight = Fight::start($g, $creature, $v->getScene(), Module::BattleContext);
        $fight->showFightActions();
        $fight->suspend();

        return $context;
    }

    /**
     * Handles the BattleOver event.
     * @param Game $g
     * @param EventContext $context
     * @return EventContext
     */
    public static function handleBattleOverEvent(Game $g, EventContext $context): EventContext
    {
        $battleIdentifier = $context->getDataField("battleIdentifier");
        $module = $g->getModuleManager()->getModule(Module::Module);

        if ($battleIdentifier == Module::BattleContext) {
            /** @var Battle $battle */
            $battle = $context->getDataField("battle");
            /** @var Viewpoint $viewpoint */
            $viewpoint = $context->getDataField("viewpoint");
            $referrerSceneId = $context->getDataField("referrerSceneId");
            /** @var Scene $scene */
            $scene = $g->getEntityManager()->getRepository(Scene::class)->find($referrerSceneId);
            $character = $g->getCharacter();

            if ($battle->getWinner() === $character) {
                $monster = $battle->getMonster();

                // Calculate how much experience the user earns.
                if ($monster instanceof Creature) {
                    [$experienceGained, $bonusExperience] = $monster->getScaledExperience(
                        $character,
                        bonusFactor: $module->getProperty(
                            name: Module::ExperienceBonusFactorProperty,
                            default: Module::ExperienceBonusFactorPropertyDefault
                        ),
                        malusFactor: $module->getProperty(
                            name: Module::ExperienceMalusFactorProperty,
                            default: Module::ExperienceMalusFactorPropertyDefault
                        ),
                    );
                } else {
                    $experienceGained = 0;
                    $bonusExperience = 0;
                }

                // Reward experience to the user
                $character->rewardExperience($experienceGained);

                // Decorate viewpoint
                $viewpoint->setTitle("You won!");

                // Gold
                $gold = $g->getDiceBag()->pseudoBell(0, $monster->getGold());
                $gold = $character->addGold($gold);

                if ($gold > 0) {
                    $viewpoint->addDescriptionParagraph(sprintf("You receive %s pieces of gold.", $gold));
                }

                // Gem loot
                $gemChance = $scene->getProperty(Forest::GemDropProbabilitySceneProperty);
                $gemChance ??= $module->getProperty(Module::GemDropProbabilityProperty, Module::GemDropProbabilityPropertyDefault);
                if ($g->getDiceBag()->chance($gemChance)) {
                    $viewpoint->addDescriptionParagraph("You find one gem on the creature's corpse.");
                    $character->addGems(1);
                }

                // Experience
                if ($bonusExperience < 0) {
                    $viewpoint->addDescriptionParagraph(sprintf(
                        "As this battle was not challenging for you, you lose %s experience from your reward.",
                        abs($bonusExperience)
                    ));
                } elseif ($bonusExperience > 0) {
                    $viewpoint->addDescriptionParagraph(sprintf(
                        "Due to the difficulty of this battle, you gain additional %s experience.",
                        abs($bonusExperience)
                    ));
                }

                $viewpoint->addDescriptionParagraph(sprintf(
                    "You defeated %s. You gain %s experience in total.",
                    $battle->getLoser()->getDisplayName(),
                    $experienceGained
                ));
            } else {
                // Remove a configurable amount of experience from the character.
                $experienceLooseFactor = $scene->getProperty(Forest::LostExperienceUponDeathSceneProperty);
                $experienceLooseFactor ??= $module->getProperty(Module::LostExperienceUponDeathProperty, Module::LostExperienceUponDeathPropertyDefault);

                $character->multiplyExperience(1 - $experienceLooseFactor);

                // Decorate viewpoint
                $viewpoint->setTitle("You died!");
                $viewpoint->addDescriptionParagraph(sprintf(
                    "You have been defeated by %s. They stand over your dead body, laughting. And you loose 10%% of your experience.",
                    $battle->getWinner()->getDisplayName()
                ));
            }

            // Display normal actions (need API later for this, from core)
            $actionGroups = [
                ActionGroup::DefaultGroup => new ActionGroup(ActionGroup::DefaultGroup, '', 0),
            ];
            $scene->getConnections()->map(function(SceneConnection $connection) use ($scene, &$actionGroups) {
                if ($connection->getOutgoingScene() === $scene) {
                    // current scene is outgoing, use incoming.
                    $connectedScene = $connection->getIncomingScene();
                    $connectionGroupName = $connection->getOutgoingConnectionGroupName();
                } else {
                    // current scene is not outgoing, thus incoming, use outgoing.
                    $connectedScene = $connection->getOutgoingScene();
                    $connectionGroupName = $connection->getIncomingConnectionGroupName();

                    // Check if the connection is unidirectional - if yes, the current scene (incoming in this branch) cannot
                    // connect to the outgoing scene.
                    if ($connection->isDirectionality(SceneConnectable::Unidirectional)) {
                        return;
                    }
                }

                $action = new Action($connectedScene->getId(), $connectedScene->getTitle());

                if ($connectionGroupName === null) {
                    $actionGroups[ActionGroup::DefaultGroup]->addAction($action);
                } else {
                    if (isset($actionGroups[$connectionGroupName])) {
                        $actionGroups[$connectionGroupName]->addAction($action);
                    } else {
                        $connectionGroup = $scene->getConnectionGroup($connectionGroupName);
                        $actionGroup = new ActionGroup($connectionGroupName, $connectionGroup->getTitle(), 0);
                        $actionGroup->addAction($action);

                        $actionGroups[$connectionGroupName] = $actionGroup;
                    }
                }
            });

            $viewpoint->setActionGroups($actionGroups);

            // Display "search" actions
            $context = self::handleMainForest($g, $context, $referrerSceneId);
        }

        return $context;
    }


    public static function handleSceneConfig(Game $g, EventContext $context): EventContext
    {
        // Get this module
        $scene = $context->getDataField("scene");

        return match ($context->getEvent()) {
            "h/lotgd/core/cli/scene-config-list/" . Forest::getNavigationEvent() => self::handleSceneConfigList($g, $context, $scene),

            "h/lotgd/core/cli/scene-config-set/" . Forest::getNavigationEvent() => match($context->getDataField("setting")) {
                self::LostExperienceUponDeathSceneProperty => self::handleExperienceLostExperienceAfterDeathScenePropertySetEvent($g, $context, $scene),
                self::GemDropProbabilitySceneProperty => self::handleExperienceGemDropProbabilityScenePropertySetEvent($g, $context, $scene),
                default => $context,
            },

            "h/lotgd/core/cli/scene-config-reset/" . Forest::getNavigationEvent() => match($context->getDataField("setting")) {
                self::LostExperienceUponDeathSceneProperty => self::handleExperienceLostExperienceAfterDeathScenePropertyResetEvent($g, $context, $scene),
                self::GemDropProbabilitySceneProperty => self::handleExperienceGemDropProbabilityScenePropertyResetEvent($g, $context, $scene),
                default => $context,
            },
        };
    }

    protected static function handleSceneConfigList(Game $g, EventContext $context, Scene $module): EventContext
    {
        // Get existing settings
        $settings = $context->getDataField("settings");

        array_push(
            $settings, [
            self::GemDropProbabilitySceneProperty,
            $module->getProperty(self::GemDropProbabilitySceneProperty, null),
            "Scene probability that a gem drops after a battle (0 ≤ x ≤ 1)",
        ], [
                self::LostExperienceUponDeathSceneProperty,
                $module->getProperty(self::LostExperienceUponDeathSceneProperty, null),
                "Amount of experience that gets lost after dying in this scene (0 ≤ x ≤ 1)",
            ]
        );

        // Set settings
        $context->setDataField("settings", $settings);

        // Return
        return $context;
    }

    protected static function handleExperienceLostExperienceAfterDeathScenePropertySetEvent(Game $g, EventContext $context, Scene $scene): EventContext
    {
        /** @var SymfonyStyle $io */
        $io = $context->getDataField("io");

        try {
            $value = floatval($context->getDataField("value"));

            if ($value < 0) {
                $io->error("Lost experience fraction must be at least 0.");
            } elseif ($value > 1) {
                $io->error("Character cannot loose more experience than he has.");
            } else {
                $scene->setProperty(self::LostExperienceUponDeathSceneProperty, $value);
                $io->success("Lost experience factor was set to {$value}.");
                $g->getLogger()->info("Lost experience factor was set to {$value}.");
            }

            $context->setDataField("return", Command::SUCCESS);
        } catch (Exception $e) {
            $context->setDataField("reason", $e->getMessage());
        }

        return $context;
    }

    protected static function handleExperienceLostExperienceAfterDeathScenePropertyResetEvent(Game $g, EventContext $context, Scene $scene): EventContext
    {
        /** @var SymfonyStyle $io */
        $io = $context->getDataField("io");

        $scene->setProperty(self::LostExperienceUponDeathSceneProperty, null);
        $context->setDataField("return", Command::SUCCESS);

        $io->success("Lost experience factor was reset.");
        $g->getLogger()->info("Lost experience factor was reset.");

        return $context;
    }

    protected static function handleExperienceGemDropProbabilityScenePropertySetEvent(Game $g, EventContext $context, Scene $scene): EventContext
    {
        /** @var SymfonyStyle $io */
        $io = $context->getDataField("io");

        try {
            $value = floatval($context->getDataField("value"));

            if ($value < 0) {
                $io->error("Gem drop probability cannot be smaller than 0.");
            } elseif ($value > 1) {
                $io->error("Gem drop probability cannot be higher than 1.");
            } else {
                $scene->setProperty(self::GemDropProbabilitySceneProperty, $value);

                $io->success("Gem drop probability was set to {$value}.");
                $g->getLogger()->info("Gem drop probability was set to {$value}.");
            }

            $context->setDataField("return", Command::SUCCESS);
        } catch (Exception $e) {
            $context->setDataField("reason", $e->getMessage());
        }

        return $context;
    }

    protected static function handleExperienceGemDropProbabilityScenePropertyResetEvent(Game $g, EventContext $context, Scene $scene): EventContext
    {
        /** @var SymfonyStyle $io */
        $io = $context->getDataField("io");

        $scene->setProperty(self::GemDropProbabilitySceneProperty, null);
        $context->setDataField("return", Command::SUCCESS);

        $io->success("Gem drop probability was reset.");
        $g->getLogger()->info("Gem rop probability was reset.");

        return $context;
    }
}