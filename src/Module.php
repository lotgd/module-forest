<?php
declare(strict_types=1);

namespace LotGD\Module\Forest;

use LotGD\Core\Game;
use LotGD\Core\Events\EventContext;
use LotGD\Core\Module as ModuleInterface;
use LotGD\Core\Models\Module as ModuleModel;
use LotGD\Core\Models\Scene;

use LotGD\Module\Village\Module as VillageModule;
use LotGD\Module\Forest\Scene\Forest;
use LotGD\Module\Forest\Scene\Healer;

class Module implements ModuleInterface {
    const ModuleIdentifier = "lotgd/module-forest";

    public static function handleEvent(Game $g, EventContext $context): EventContext
    {
        $event = $context->getEvent();

        switch($event) {
            case "h/lotgd/core/navigate-to/lotgd/module-new-day/newDay":
                $viewpoint = $context->getDataField("viewpoint");
                $viewpoint->addDescriptionParagraph("You feel energized!");
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

        $g->getEntityManager()->flush();
    }

    public static function onUnregister(Game $g, ModuleModel $module)
    {
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

        $g->getEntityManager()->flush();
    }
}
