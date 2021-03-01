<?php
declare(strict_types=1);

namespace LotGD\Module\Forest;

use Doctrine\DBAL\ConnectionException;
use Exception;
use LotGD\Core\Events\EventContext;
use LotGD\Core\Game;
use LotGD\Core\Models\Module as ModuleModel;
use LotGD\Core\Models\Scene;
use LotGD\Core\Module as ModuleInterface;
use LotGD\Module\Forest\Models\Creature;
use LotGD\Module\Forest\SceneTemplates\Forest;
use LotGD\Module\Forest\SceneTemplates\Healer;
use LotGD\Module\Res\Fight\Module as FightModule;
use LotGD\Module\Village\SceneTemplates\VillageScene;
use SplFileObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

class Module implements ModuleInterface {
    const Module = "lotgd/module-forest";

    const BattleContext = self::Module . "/battle";

    // Events
    const HookForestNavigation = "h/" . self::Module . "/forest-navigation";

    // Module properties
    const GeneratedSceneProperty = "generatedScenes";
    const ExperienceBonusFactorProperty = "experienceBonus";
    const ExperienceBonusFactorPropertyDefault = 0.25;
    const ExperienceMalusFactorProperty = "experienceMalus";
    const ExperienceMalusFactorPropertyDefault = 0.25;
    const GemDropProbabilityProperty = "gemDropProbability";
    const GemDropProbabilityPropertyDefault = 0.04;
    const LostExperienceUponDeathProperty = "lostExperienceUponDeath";
    const LostExperienceUponDeathPropertyDefault = 0.1;


    public static function handleEvent(Game $g, EventContext $context): EventContext
    {
        return match ($context->getEvent()) {
            "h/lotgd/core/navigate-to/" . Forest::Template => Forest::handleEvent($g, $context),
            "h/lotgd/core/navigate-to/" . Healer::Template => Healer::handleEvent($g, $context),
            FightModule::HookBattleOver => Forest::handleBattleOverEvent($g, $context),
            "h/lotgd/core/cli/module-config-list/" . self::Module,
                "h/lotgd/core/cli/module-config-set/" . self::Module,
                "h/lotgd/core/cli/module-config-reset/" . self::Module => self::handleModuleConfig($g, $context),
            "h/lotgd/core/cli/scene-config-list/" . Forest::getNavigationEvent(),
                "h/lotgd/core/cli/scene-config-set/" . Forest::getNavigationEvent(),
                "h/lotgd/core/cli/scene-config-reset/" . Forest::getNavigationEvent() => Forest::handleSceneConfig($g, $context),
            default => $context,
        };
    }

    protected static function handleModuleConfig(Game $g, EventContext $context): EventContext
    {
        // Get this module
        $module = $g->getModuleManager()->getModule(self::Module);

        if (!$module) {
            $g->getLogger()->error("Module called, but appears not to be installed (".self::Module.")");
            return $context;
        }

        return match ($context->getEvent()) {
            "h/lotgd/core/cli/module-config-list/" . self::Module => self::handleModuleConfigList($g, $context, $module),
            "h/lotgd/core/cli/module-config-set/" . self::Module => match($context->getDataField("setting")) {
                self::ExperienceBonusFactorProperty => self::handleExperienceBonusFactorModulePropertySetEvent($g, $context, $module),
                self::ExperienceMalusFactorProperty => self::handleExperienceMalusFactorModulePropertySetEvent($g, $context, $module),
                self::LostExperienceUponDeathProperty => self::handleExperienceLostExperienceAfterDeathModulePropertySetEvent($g, $context, $module),
                self::GemDropProbabilityProperty => self::handleExperienceGemDropProbabilityModulePropertySetEvent($g, $context, $module),
                default => $context,
            },
            "h/lotgd/core/cli/module-config-reset/" . self::Module => match($context->getDataField("setting")) {
                self::ExperienceBonusFactorProperty => self::handleExperienceBonusFactorModulePropertyResetEvent($g, $context, $module),
                self::ExperienceMalusFactorProperty => self::handleExperienceMalusFactorPropertyModuleResetEvent($g, $context, $module),
                self::LostExperienceUponDeathProperty => self::handleExperienceLostExperienceAfterDeathModulePropertyResetEvent($g, $context, $module),
                self::GemDropProbabilityProperty => self::handleExperienceGemDropProbabilityModulePropertyResetEvent($g, $context, $module),
                default => $context,
            },
        };
    }

    protected static function handleModuleConfigList(Game $g, EventContext $context, ModuleModel $module): EventContext
    {
        // Get existing settings
        $settings = $context->getDataField("settings");

        array_push(
            $settings, [
                self::ExperienceBonusFactorProperty,
                $module->getProperty(self::ExperienceBonusFactorProperty, self::ExperienceBonusFactorPropertyDefault),
                "Additional experience gained for harsher battles (as fraction, should be > 0)",
            ], [
                self::ExperienceMalusFactorProperty,
                $module->getProperty(self::ExperienceMalusFactorProperty, self::ExperienceMalusFactorPropertyDefault),
                "Experience reduction for easier battles (fraction, should be > 0, must be ≤ 1)",
            ], [
                self::GemDropProbabilityProperty,
                $module->getProperty(self::GemDropProbabilityProperty, self::GemDropProbabilityPropertyDefault),
                "Global probability that a gem drops after a battle (0 ≤ x ≤ 1)",
            ], [
                self::LostExperienceUponDeathProperty,
                $module->getProperty(self::LostExperienceUponDeathProperty, self::LostExperienceUponDeathPropertyDefault),
                "Amount of experience that gets lost after dying (0 ≤ x ≤ 1)",
            ]
        );

        // Set settings
        $context->setDataField("settings", $settings);

        // Return
        return $context;
    }

    protected static function handleExperienceBonusFactorModulePropertySetEvent(Game $g, EventContext $context, ModuleModel $module): EventContext
    {
        /** @var SymfonyStyle $io */
        $io = $context->getDataField("io");

        try {
            $value = floatval($context->getDataField("value"));

            if ($value < 0) {
                $io->warning("A negative bonus factor will lead to less experience earned.");
            }

            $module->setProperty(self::ExperienceBonusFactorProperty, $value);
            $context->setDataField("return", Command::SUCCESS);

            $io->success("Bonus experience factor was set to {$value}.");
            $g->getLogger()->info("Bonus experience factor was set to {$value}.");
        } catch (Exception $e) {
            $context->setDataField("reason", $e->getMessage());
        }

        return $context;
    }

    protected static function handleExperienceBonusFactorModulePropertyResetEvent(Game $g, EventContext $context, ModuleModel $module): EventContext
    {
        /** @var SymfonyStyle $io */
        $io = $context->getDataField("io");

        $module->setProperty(self::ExperienceBonusFactorProperty, self::ExperienceBonusFactorPropertyDefault);
        $context->setDataField("return", Command::SUCCESS);

        $io->success("Experience bonus factor was reset to ".(string)self::ExperienceBonusFactorPropertyDefault.".");
        $g->getLogger()->info("Experience bonus factor was reset to ".(string)self::ExperienceBonusFactorPropertyDefault.".");

        return $context;
    }

    protected static function handleExperienceMalusFactorModulePropertySetEvent(Game $g, EventContext $context, ModuleModel $module): EventContext
    {
        /** @var SymfonyStyle $io */
        $io = $context->getDataField("io");

        try {
            $value = floatval($context->getDataField("value"));

            if ($value < 0) {
                $io->warning("A negative malus factor will lead to more experience earned.");
            }

            if ($value >= 1) {
                $io->error("Experience malus cannot be bigger than 1. This would lead to the character loosing experience in total.");
                $context->setDataField("return", Command::SUCCESS);
            } else {
                $module->setProperty(self::ExperienceMalusFactorProperty, $value);
                $context->setDataField("return", Command::SUCCESS);

                $io->success("Malus experience factor was set to {$value}.");
                $g->getLogger()->info("Malus experience factor was set to {$value}.");
            }
        } catch (Exception $e) {
            $context->setDataField("reason", $e->getMessage());
        }

        return $context;
    }

    protected static function handleExperienceMalusFactorPropertyModuleResetEvent(Game $g, EventContext $context, ModuleModel $module): EventContext
    {
        /** @var SymfonyStyle $io */
        $io = $context->getDataField("io");

        $module->setProperty(self::ExperienceMalusFactorProperty, self::ExperienceMalusFactorPropertyDefault);
        $context->setDataField("return", Command::SUCCESS);

        $io->success("Experience malus factor was reset to ".(string)self::ExperienceMalusFactorPropertyDefault.".");
        $g->getLogger()->info("Experience malus was factor reset to ".(string)self::ExperienceMalusFactorPropertyDefault.".");

        return $context;
    }

    protected static function handleExperienceLostExperienceAfterDeathModulePropertySetEvent(Game $g, EventContext $context, ModuleModel $module): EventContext
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
                $module->setProperty(self::LostExperienceUponDeathProperty, $value);
                $io->success("Lost experience factor was set to {$value}.");
                $g->getLogger()->info("Lost experience factor was set to {$value}.");
            }

            $context->setDataField("return", Command::SUCCESS);
        } catch (Exception $e) {
            $context->setDataField("reason", $e->getMessage());
        }

        return $context;
    }

    protected static function handleExperienceLostExperienceAfterDeathModulePropertyResetEvent(Game $g, EventContext $context, ModuleModel $module): EventContext
    {
        /** @var SymfonyStyle $io */
        $io = $context->getDataField("io");

        $module->setProperty(self::LostExperienceUponDeathProperty, self::LostExperienceUponDeathPropertyDefault);
        $context->setDataField("return", Command::SUCCESS);

        $io->success("Lost experience factor was reset to ".(string)self::LostExperienceUponDeathPropertyDefault.".");
        $g->getLogger()->info("Lost experience factor was reset to ".(string)self::LostExperienceUponDeathPropertyDefault.".");

        return $context;
    }

    protected static function handleExperienceGemDropProbabilityModulePropertySetEvent(Game $g, EventContext $context, ModuleModel $module): EventContext
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
                $module->setProperty(self::GemDropProbabilityProperty, $value);

                $io->success("Gem drop probability was set to {$value}.");
                $g->getLogger()->info("Gem drop probability was set to {$value}.");
            }

            $context->setDataField("return", Command::SUCCESS);
        } catch (Exception $e) {
            $context->setDataField("reason", $e->getMessage());
        }

        return $context;
    }

    protected static function handleExperienceGemDropProbabilityModulePropertyResetEvent(Game $g, EventContext $context, ModuleModel $module): EventContext
    {
        /** @var SymfonyStyle $io */
        $io = $context->getDataField("io");

        $module->setProperty(self::GemDropProbabilityProperty, self::GemDropProbabilityPropertyDefault);
        $context->setDataField("return", Command::SUCCESS);

        $io->success("Gem drop probability was reset to ".(string)self::GemDropProbabilityPropertyDefault.".");
        $g->getLogger()->info("Gem rop probability reset was to ".(string)self::GemDropProbabilityPropertyDefault.".");

        return $context;
    }

    /**
     * Installation procedure
     * @param Game $g
     * @param ModuleModel $module
     * @throws Exception
     */
    public static function onRegister(Game $g, ModuleModel $module)
    {
        $em = $g->getEntityManager();

        // We use this to save our automatically generated scenes.
        $generatedScenes = ["forest" => [], "healer" => []];

        // Get registered village scenes.
        $villageScenes = $em->getRepository(Scene::class)
            ->findBy(["template" => VillageScene::class]);

        // For each village scene, we create a forest and the corresponding healer.
        foreach ($villageScenes as $villageScene) {
            [$forestScene, $healerScene] = Forest::create();

            // Connect forest to the village
            if ($villageScene->hasConnectionGroup(VillageScene::Groups[0])) {
                $villageScene
                    ->getConnectionGroup(VillageScene::Groups[0])
                    ->connect($forestScene->getConnectionGroup(Forest::Groups["back"][0]));
            } else {
                $villageScene->connect($forestScene->getConnectionGroup(Forest::Groups["back"][0]));
            }

            // Persist scenes, but don't flush
            $em->persist($forestScene);
            $em->persist($healerScene);
            $em->persist($forestScene->getTemplate());
            $em->persist($healerScene->getTemplate());

            // Remember the created scenes
            $generatedScenes["forest"][] = $forestScene->getId();
            $generatedScenes["healer"][] = $healerScene->getId();
        }

        // Read in creatures
        $file = new SplFileObject(__DIR__ . "/../res/creatures.csv");
        $titles = $file->fgetcsv(","); // must fetch title line first
        while (!$file->eof()) {
            $data = $file->fgetcsv(",");
            $creature = new Creature(
                name: $data[0],
                weapon: $data[1],
                level: intval($data[2]),
                attack: intval($data[3]),
                defense: intval($data[4]),
                maxHealth: intval($data[5]),
                experience: intval($data[6]),
                gold: intval($data[7]),
            );

            $g->getEntityManager()->persist($creature);
        }

        $module->setProperty(self::GeneratedSceneProperty, $generatedScenes);
    }

    /**
     * Module removal procedure
     * @param Game $g
     * @param ModuleModel $module
     * @throws ConnectionException
     * @throws \Doctrine\DBAL\Exception
     */
    public static function onUnregister(Game $g, ModuleModel $module)
    {
        $em = $g->getEntityManager();

        // Get our generated scenes from the module properties
        $generatedScenes = $module->getProperty(self::GeneratedSceneProperty, ["forest" => [], "healer" => []]);

        // Get all forest and healer scenes
        $scenes = $em->getRepository(Scene::class)->findBy(["template" => [Forest::class, Healer::class]]);

        // Run through all scenes
        foreach ($scenes as $scene) {
            $sceneType = match ($scene->getTemplate()->getClass()) {
                Forest::class => "forest",
                Healer::class => "healer",
            };

            if (in_array($scene->getId(), $generatedScenes[$sceneType])) {
                // Remove automatically registered scenes
                $em->remove($scene);
                $em->remove($scene->getTemplate());
            } else {
                // Set manually created scenes to not have this scene templates
                $scene->setTemplate(null);
            }
        }

        // empty creatures
        // @ToDo: Put this into a method.
        $cmd = $em->getClassMetadata(Creature::class);
        $connection = $em->getConnection();
        $dbPlatform = $connection->getDatabasePlatform();
        $connection->beginTransaction();
        $q = $dbPlatform->getTruncateTableSql($cmd->getTableName());
        $connection->executeUpdate($q);
        $connection->commit();

        $g->getEntityManager()->flush();
    }
}
