<?php
declare(strict_types=1);

namespace LotGD\Module\Forest\Managers;

use LotGD\Core\Game;
use LotGD\Module\Forest\Models\Creature;

/**
 * Class CreatureManager. This class offers an easy api for getting a randomized creature from the database with a selected level.
 * @package LotGD\Module\Forest\Managers
 */
class CreatureManager
{
    private $game;

    const FightDifficultyEasy = 0;
    const FightDifficultyNormal = 10;
    const FightDifficultyHard = 20;

    public function __construct(Game $game)
    {
        $this->game = $game;
    }

    /**
     * Returns a possibly random creature for a certain level.
     * @param int $level
     * @param int $difficulty
     * @param bool $randomizeLevel
     * @return Creature
     */
    public function getCreature(int $level, int $difficulty = self::FightDifficultyNormal, bool $randomizeLevel = True): Creature
    {
        switch($difficulty) {
            case self::FightDifficultyEasy:
                $level--;
                break;

            case self::FightDifficultyNormal:
                if ($this->game->getDiceBag()->chance(0.25)) {
                    if ($this->game->getDiceBag()->chance(0.125)) {
                        $level++;
                    }

                    if ($this->game->getDiceBag()->chance(0.25)) {
                        $level--;
                    }
                }
                break;

            case self::FightDifficultyHard:
                $level++;
                break;
        }

        $creatures = $this->game->getEntityManager()->getRepository(Creature::class)
            ->findBy(["level" => $level]);

        if (count($creatures) === 0) {
            $character = $this->game->getCharacter();
            $creature = new Creature();
            $creature->setName(sprintf("%s's evil Doppelganger"));
            $creature->setWeapon("Evil aura");
            $creature->setAttack($character->getAttack($this->game));
            $creature->setDefense($character->getDefense($this->game));
            $creature->setLevel($character->getLevel());
            $creature->setMaxHealth($character->getMaxHealth());
            $creature->setHealth($creature->getMaxHealth());

            // No detaching needed since it is new.
        } else {
            $creature = $creatures[$this->game->getDiceBag()->dice(1, count($creatures)) - 1];

            // Detach the creature: User is fighting against a clone of the creature with it's own health pool, possibly upscaled
            $this->game->getEntityManager()->detach($creature);
        }

        return $creature;
    }
}