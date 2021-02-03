<?php
declare(strict_types=1);

namespace LotGD\Module\Forest;

use SplFileObject;
use LotGD\Core\Game;
use LotGD\Core\Events\EventContext;
use LotGD\Core\Module as ModuleInterface;
use LotGD\Core\Models\Module as ModuleModel;
use LotGD\Core\Models\Scene;
use LotGD\Module\Village\Module as VillageModule;
use LotGD\Module\Village\SceneTemplates\VillageScene;
use LotGD\Module\Res\Fight\Module as FightModule;
use LotGD\Module\Forest\Models\Creature;
use LotGD\Module\Forest\SceneTemplates\Forest;
use LotGD\Module\Forest\SceneTemplates\Healer;

class Module implements ModuleInterface {
    const Module = "lotgd/module-forest";

    const BattleContext = self::Module . "/battle";
    const HookForestNavigation = "h/" . self::Module . "/forest-navigation";
    const GeneratedSceneProperty = "generatedScenes";

    public static function handleEvent(Game $g, EventContext $context): EventContext
    {
        $event = $context->getEvent();

        switch($event) {
            case "h/lotgd/core/navigate-to/" . Forest::Template:
                $context = Forest::handleEvent($g, $context);
                break;

            case "h/lotgd/core/navigate-to/" . Healer::Template:
                $context = Healer::handleEvent($g, $context);
                break;

            case FightModule::HookBattleOver:
                $context = Forest::handleBattleOverEvent($g, $context);
                break;
        }
        
        return $context;
    }
    
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
        $file = new SplFileObject(__DIR__ . "/../res/creatures.tsv");
        $titles = $file->fgetcsv("\t"); // must fetch title line first
        while (!$file->eof()) {
            $data = $file->fgetcsv("\t");
            $data = [
                "name" => $data[0],
                "weapon" => $data[1],
                "level" => intval($data[2]),
                "attack" => intval($data[3]),
                "defense" => intval($data[4]),
                "maxHealth" => intval($data[5]),
            ];

            $creature = call_user_func([Creature::class, "create"], $data);
            $g->getEntityManager()->persist($creature);
        }

        $module->setProperty(self::GeneratedSceneProperty, $generatedScenes);
    }

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
