<?php
declare(strict_types=1);

namespace LotGD\Module\Forest\Scenes;

use LotGD\Core\Action;
use LotGD\Core\ActionGroup;
use LotGD\Core\Events\EventContext;
use LotGD\Core\Game;
use LotGD\Core\Models\Character;
use LotGD\Core\Models\Scene;
use LotGD\Core\Models\SceneConnectionGroup;
use LotGD\Core\Models\Viewpoint;

/**
 * Handles Scenes related to the Healer's Hut
 * Class Healer
 * @package LotGD\Module\Forest\Scenes
 */
class Healer
{
    const Template = "lotgd/module-forest/healer";
    const Groups = [
        "healing" => ["lotgd/module-forest/healer/healing", "Potions"],
        "back" => ["lotgd/module-forest/healer/back", "Back"],
    ];

    /**
     * Creates a Healer's Hut scene.
     * @return Scene
     */
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

    /**
     * Handles the healing scene by delegating it to further sub methods
     * @param Game $g
     * @param EventContext $context
     * @return EventContext
     */
    public static function handleEvent(Game $g, EventContext $context): EventContext
    {
        /** @var array $parameters */
        $parameters = $context->getDataField("parameters");

        if (isset($parameters["healing"])) {
            return self::handleHealScene($g, $context);
        } else {
            return self::handleMainScene($g, $context);
        }
    }

    /**
     * Handles the scene if no healing option has been selected.
     * @param Game $g
     * @param EventContext $context
     * @return EventContext
     */
    protected static function handleMainScene(Game $g, EventContext $context): EventContext
    {
        /** @var Viewpoint $v */
        $v = $context->getDataField("viewpoint");
        /** @var Character $c */
        $c = $g->getCharacter();

        if ($c->isAlive() === false) {
            $v->addDescriptionParagraph('"See you, I do.  Before you did see me, I think, hmm?" the old thing remarks.
                "Know you, I do; healing you need. Willing to heal am I, but doing for you something I cannot."
                
                "Uh, um. Why?" you ask, ready to be rid of the smelly old thing.
                
                "Slain you were. Doing for the dead nothing I can. Leaving you must."');
        } elseif ($c->getHealth() < $c->getMaxHealth()) {
            $healActions = [
                new Action($v->getScene()->getId(), "Complete Healing", ["healing" => "all"]),
            ];

            if ($v->hasActionGroup(self::Groups["healing"][0])) {
                foreach ($healActions as $action) {
                    $v->addActionToGroupId($action, self::Groups["healing"][0]);
                }
            } else {
                $group = new ActionGroup(self::Groups["healing"][0], self::Groups["healing"][1], 0);
                $group->setActions($healActions);
                $v->addActionGroup($group);
            }

            $v->addDescriptionParagraph('"See you, I do.  Before you did see me, I think, hmm?" the old thing remarks.
                "Know you, I do; healing you seek.  Willing to heal am I, but only if willing to pay are you."
                
                "Uh, um. How much?" you ask, ready to be rid of the smelly old thing.
                
                The old being thumps your ribs with a gnarly staff. "For you... 0 gold pieces for a complete heal!!" it says 
                as it bends over and pulls a clay vial from behind a pile of skulls sitting in the corner.
                
                The view of the thing bending over to remove the vial almost does enough mental damage to require a larger potion.
                "I also have some, erm... \'bargain\' potions available", it says as it gestures at a pile of dusty, cracked vials.
                "A certain percent of your damage heal they will."');
        } elseif ($c->getHealth() > $c->getMaxHealth()) {
            // Over max health, we steal some health points.
            $v->addDescriptionParagraph('The old creature glances at you, then in a whirlwind of movement
            that catches you completely off guard, brings its gnarled staff squarely in contact with the back of your head.
            You gasp as you collapse to the ground.
            
            Slowly you open your eyes and realize the beast is emptying the last drops of a clay vial down your throat.
            
            "No charge for that potion." is all it has to say.
            
            You feel a strong urge to leave as quickly as you can.
            ');

            $c->setHealth($c->getMaxHealth());
        } else {
            $v->addDescriptionParagraph('The old creature grunts as it looks your way. "Need a potion, you do not.
            Wonder why you bother me, I do." says the hideous thing. The aroma of its breath makes you wish you hadn\'t come 
            in there in the first place. You think you had best leave.');
        }

        return $context;
    }

    /**
     * Handles the scene of a healing option has been selected.
     * @param Game $g
     * @param EventContext $context
     * @return EventContext
     */
    protected static function handleHealScene(Game $g, EventContext $context): EventContext
    {
        /** @var Viewpoint $v */
        $v = $context->getDataField("viewpoint");
        /** @var Character $c */
        $c = $g->getCharacter();

        $healType = $context->getDataField("parameters")["healing"];

        $v->clearDescription();
        $v->addDescriptionParagraph('With a grimace, you up-end the potion the creature hands you, and despite 
            the foul flactor, you feel a warmth spreading through your veins as your muscles knit back together.
            Staggering some you are ready to be out of here.');

        // No cost for now
        if ($healType == "all") {
            $c->setHealth($c->getMaxHealth());
        }

        return $context;
    }
}