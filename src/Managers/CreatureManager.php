<?php
declare(strict_types=1);

namespace LotGD\Module\Forest\Managers;

use LotGD\Core\Game;
use LotGD\Module\Forest\Models\Creature;

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

        // @ToDo: Create a dummy creature in case there are none.
        $creature = $creatures[$this->game->getDiceBag()->dice(1, count($creatures)) - 1];

        // Detach the creature: User is fighting against a clone of the creature with it's own health pool, possibly upscaled
        $this->game->getEntityManager()->detach($creature);

        return $creature;
    }
}