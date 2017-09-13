<?php
declare(strict_types=1);

namespace LotGD\Module\Forest\Scene;

use LotGD\Core\Models\Scene;
use LotGD\Core\Models\SceneConnectionGroup;

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
}