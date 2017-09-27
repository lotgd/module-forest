<?php
declare(strict_types=1);

namespace LotGD\Module\Forest\Scenes;

use LotGD\Core\Models\Scene;
use LotGD\Core\Models\SceneConnectionGroup;

class Healer
{
    const Template = "lotgd/module-forest/healer";
    const Groups = [
        "healing" => ["lotgd/module-forest/healer/healing", "Potions"],
        "back" => ["lotgd/module-forest/healer/back", "Back"],
    ];

    public static function create(): Scene
    {
        $scene = Scene::create([
                "template" => self::Template,
                "title" => "Healer's Hut",
                "description" => "You duck into the small smoke-filled grass hut.
                The pungent aroma makes you cough, attracting the attention of a grizzled old person that
                does a remarkable job of reminding you of a rock, which probably explains why you didn't 
                notice them until now. Couldn't be your failure as a warrior. Nope, definitely not.",
            ]
        );

        foreach (self::Groups as $key => $val) {
            $scene->addConnectionGroup(new SceneConnectionGroup($val[0], $val[1]));
        }

        return $scene;
    }
}