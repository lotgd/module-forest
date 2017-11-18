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
use LotGD\Module\Forest\Models\Creature;
use LotGD\Module\Forest\Scenes\Forest;
use LotGD\Module\Forest\Scenes\Healer;

const MODULE = "lotgd/module-forest";

class Module implements ModuleInterface {
    const ModuleIdentifier = MODULE;
    const CharacterPropertyBattleState = MODULE . "/battle-state";

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
        }
        
        return $context;
    }
    
    public static function onRegister(Game $g, ModuleModel $module)
    {
        $villageScenes = $g->getEntityManager()->getRepository(Scene::class)
            ->findBy(["template" => VillageModule::VillageScene]);

        foreach ($villageScenes as $villageScene) {
            [$forestScene, $healerScene] = Forest::create();

            // Connect forest to the village
            if ($villageScene->hasConnectionGroup(VillageModule::Groups[0])) {
                $villageScene
                    ->getConnectionGroup(VillageModule::Groups[0])
                    ->connect($forestScene->getConnectionGroup(Forest::Groups["back"][0]));
            } else {
                $villageScene->connect($forestScene->getConnectionGroup(Forest::Groups["back"][0]));
            }

            $g->getEntityManager()->persist($forestScene);
            $g->getEntityManager()->persist($healerScene);
        }

        // Read in creatures
        $file = new SplFileObject(__DIR__ . "/../res/creatures.tsv");
        $titles = $file->fgetc("\t"); // must fetch title line first
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

        $g->getEntityManager()->flush();
    }

    public static function onUnregister(Game $g, ModuleModel $module)
    {
        $em = $g->getEntityManager();

        // delete healer
        $scenes = $g->getEntityManager()->getRepository(Scene::class)
            ->findBy(["template" => Forest::Template]);
        foreach($scenes as $scene) {
            $g->getEntityManager()->remove($scene);
        }

        // delete forest
        $scenes = $g->getEntityManager()->getRepository(Scene::class)
            ->findBy(["template" => Healer::Template]);
        foreach($scenes as $scene) {
            $g->getEntityManager()->remove($scene);
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
