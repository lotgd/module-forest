<?php
declare(strict_types=1);

namespace LotGD\Module\Forest\SceneTemplates;

use LotGD\Core\Action;
use LotGD\Core\ActionGroup;
use LotGD\Core\Events\EventContext;
use LotGD\Core\Game;
use LotGD\Core\Models\Character;
use LotGD\Core\Models\Scene;
use LotGD\Core\Models\SceneConnectionGroup;
use LotGD\Core\Models\SceneTemplate;
use LotGD\Core\Models\Viewpoint;
use LotGD\Core\SceneTemplates\SceneTemplateInterface;

use LotGD\Module\Forest\Module;

/**
 * Handles Scenes related to the Healer's Hut
 * Class Healer
 * @package LotGD\Module\Forest\Scenes
 */
class Healer implements SceneTemplateInterface
{
    const Template = "lotgd/module-forest/healer";
    const Groups = [
        "healing" => ["lotgd/module-forest/healer/healing", "Potions"],
        "back" => ["lotgd/module-forest/healer/back", "Back"],
    ];

    private static ?SceneTemplate $template = null;

    public static function getNavigationEvent(): string
    {
        return self::Template;
    }

    public static function getHealCosts(Character $character)
    {
        $base = 10;
        $logLevel = log($character->getLevel());
        $damage = $character->getMaxHealth() - $character->getHealth();

        return round($logLevel * ($base + $damage), 0);
    }

    /**
     * Creates a Healer's Hut scene.
     * @return Scene
     */
    public static function create(): Scene
    {
        if (self::$template === null) {
            self::$template = new SceneTemplate(self::class, Module::Module);
        }

        $scene = new Scene(
            title: "Healer's Hut",
            description: <<<TXT
                You duck into the small smoke-filled grass hut.
                
                The pungent aroma makes you cough, attracting the attention of a grizzled old person that
                does a remarkable job of reminding you of a rock, which probably explains why you didn't 
                notice them until now. Couldn't be your failure as a warrior. Nope, definitely not."
                
                {% if Character.health > 0 %}
                    {% if Character.health < Character.maxHealth %}
                        See you, I do.  Before you did see me, I think, hmm?" the old thing remarks.
                        "Know you, I do; healing you seek.  Willing to heal am I, but only if willing to pay are you."
                        
                        "Uh, um. How much?" you ask, ready to be rid of the smelly old thing.
                        
                        The old being thumps your ribs with a gnarly staff. "For you... 0 gold pieces for a complete heal!!" it says 
                        as it bends over and pulls a clay vial from behind a pile of skulls sitting in the corner.
                        
                        The view of the thing bending over to remove the vial almost does enough mental damage to require a larger potion.
                        "I also have some, erm... \'bargain\' potions available", it says as it gestures at a pile of dusty, cracked vials.
                        "A certain percent of your damage heal they will."
                    {% elseif Character.health > Character.maxHealth %}
                        The old creature glances at you, then in a whirlwind of movement
                        that catches you completely off guard, brings its gnarled staff squarely in contact with the back of your head.
                        You gasp as you collapse to the ground.
                        
                        Slowly you open your eyes and realize the beast is emptying the last drops of a clay vial down your throat.
                        
                        "No charge for that potion." is all it has to say.
                        
                        You feel a strong urge to leave as quickly as you can.
                    {% else %}
                        The old creature grunts as it looks your way. "Need a potion, you do not. Wonder why you bother 
                        me, I do." says the hideous thing. The aroma of its breath makes you wish you hadn\'t come 
                        in there in the first place. You think you had best leave.
                    {% endif %}
                {% else %}
                     "See you, I do.  Before you did see me, I think, hmm?" the old thing remarks.
                    "Know you, I do; healing you need. Willing to heal am I, but doing for you something I cannot."
                    
                    "Uh, um. Why?" you ask, ready to be rid of the smelly old thing.
                    
                    "Slain you were. Doing for the dead nothing I can. Leaving you must."
                {% endif %}
            TXT,
            template: self::$template,
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
        $v->setDataField("healCosts", self::getHealCosts($c));

        if ($c->isAlive() === false) {
            // Nothing to add, text has been moved to scene description.
        } elseif ($c->getHealth() < $c->getMaxHealth()) {
            $healActions = [
                new Action($v->getScene()->getId(), "Complete Healing ({{ Viewpoint.data.healCosts }} Gold)", ["healing" => 100]),
            ];

            // Add partial healings
            $wouldHealList = [$c->getMaxHealth() - $c->getHealth() * 1.0 => true];
            for ($i=9; $i>0; $i--) {
                $healPercentage = $i*10;
                $healFraction = $i/10;

                $wouldHeal = round(($c->getMaxHealth() - $c->getHealth()) * $healFraction, 0);

                // Break if the heal amount would be < 0. There is no need to try out smaller differences.
                if ($wouldHeal <= 0) {
                    break;
                }

                // Continue if this heal amount has already been registered. No need to offer to prices.
                if (isset($wouldHealList[$wouldHeal])) {
                    continue;
                }

                // We mark "heal amount" as already done.
                $wouldHealList[$wouldHeal] = true;

                $healActions[] = new Action(
                    $v->getScene()->getId(),
                    "Heal {{ Action.parameters.healing }}% ({{ (Viewpoint.data.healCosts * Action.parameters.healing / 100) | round }} Gold)",
                    ["healing" => $healPercentage]
                );
            }

            $healActionGroup = $v->findActionGroupById(self::Groups["healing"][0]);
            if ($healActionGroup) {
                foreach ($healActions as $action) {
                    $v->addActionToGroupId($action, self::Groups["healing"][0]);
                }
            } else {
                $group = new ActionGroup(self::Groups["healing"][0], self::Groups["healing"][1], 0);
                $group->setActions($healActions);
                $v->addActionGroup($group);
            }
        } elseif ($c->getHealth() > $c->getMaxHealth()) {
            // Over max health, we steal some health points.
            $c->setHealth($c->getMaxHealth());
        } else {
            // Full health, change nothing
            // The dynamic scene description should take care of this.
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

        $healFraction = $context->getDataField("parameters")["healing"]/100;
        $healAmount = round(($c->getMaxHealth() - $c->getHealth()) * $healFraction, 0);
        $healCosts = self::getHealCosts($c);
        $actualCosts = round($healCosts * $healFraction, 0);

        $v->setDataField("healCosts", $actualCosts);
        $v->setDataField("healAmount", $healAmount);
        $v->clearDescription();

        if ($c->getGold() < $actualCosts) {
            $v->setDescription("The old creature pierces you with a gaze hard and cruel. Your lightning quick 
            reflexes enable you to dodge the blow from its gnarled staff. Perhaps you should get some more money 
            before you attempt to engage in local commerce.
            
            You recall that the creature had asked for {{ Viewpoint.data.healCosts }} gold.");
        } else {
            $c->heal((int)$healAmount);
            $g->getLogger()->debug("User healed for {$healAmount} points in exchange for {$healCosts} gold.");
            $v->addDescriptionParagraph('With a grimace, you up-end the potion the creature hands you, and despite 
            the foul flactor, you feel a warmth spreading through your veins as your muscles knit back together.
            Staggering some you are ready to be out of here.
            
            You have been healed for {% if Viewpoint.data.healAmount == 1 %}one point{% else %}{{ Viewpoint.data.healAmount }} points{% endif %}!');
        }

        return $context;
    }
}