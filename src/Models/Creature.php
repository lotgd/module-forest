<?php
declare(strict_types=1);

namespace LotGD\Module\Forest\Models;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Table;
use JetBrains\PhpStorm\ArrayShape;
use LotGD\Core\BuffList;
use LotGD\Core\Game;
use LotGD\Core\Models\BasicEnemy;
use LotGD\Core\Models\Character;
use LotGD\Core\Models\CreateableInterface;
use LotGD\Core\Tools\Model\Creator;
use LotGD\Core\Tools\Model\Deletor;

/**
 * Class Creature
 * @package LotGD\Module\Forest\Models
 * @Entity
 * @Table(name="Creatures")
 */
class Creature extends BasicEnemy
{
    use Deletor;

    /** @Column(type="string") */
    protected $weapon;
    /** @Column(type="integer") */
    protected $attack;
    /** @Column(type="integer") */
    protected $defense;
    /** @Column(type="integer") */
    protected $maxHealth;
    protected $bufflist;

    const ExperienceTable = [
        1 => 14,
        2 => 24,
        3 => 34,
        4 => 45,
        5 => 55,
        6 => 66,
        7 => 77,
        8 => 89,
        9 => 101,
        10 => 114,
        11 => 127,
        12 => 141,
        13 => 135,
        14 => 172,
        15 => 189,
        16 => 207,
        17 => 223,
        18 => 249,
    ];

    public function __construct(
        string $name,
        string $weapon,
        int $level = 1,
        int $attack = 1,
        int $defense = 1,
        int $maxHealth = 10,
    ) {
        parent::__construct();

        $this->name = $name;
        $this->level = $level;
        $this->weapon = $weapon;
        $this->attack = $attack;
        $this->defense = $defense;
        $this->maxHealth = $maxHealth;
        $this->bufflist = new BuffList(new ArrayCollection());
    }

    /**
     * Sets the name of the creature
     * @param string $name
     */
    public function setName(string $name)
    {
        $this->name = $name;
    }

    /**
     * Gives the name of the current creature.
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Sets the level of a creature.
     * @param int $level
     */
    public function setLevel(int $level)
    {
        $this->level = $level;
    }

    /**
     * Returns the base attack level of the creature.
     * @param int $attack
     */
    public function setAttack(int $attack)
    {
        $this->attack = $attack;
    }

    /**
     * Returns the attack value of a creature, possibly changed by events.
     * @param bool $ignoreBuffs
     * @return int
     */
    public function getAttack(bool $ignoreBuffs = false): int
    {
        return $this->attack;
    }

    /**
     * Sets the base defense level of the creature.
     * @param int $defense
     */
    public function setDefense(int $defense)
    {
        $this->defense = $defense;
    }

    /**
     * Returns the defense value of the creature, possibly changed by events.
     * @param bool $ignoreBuffs
     * @return int
     */
    public function getDefense(bool $ignoreBuffs = false): int
    {
        return $this->defense;
    }

    /**
     * Sets the maximum health value of the creature.
     * @param int $maxHealth
     */
    public function setMaxHealth(int $maxHealth): void
    {
        $this->maxHealth = $maxHealth;
    }

    /**
     * Returns the maximum health value of the creature.
     * @return int
     */
    public function getMaxHealth(): int
    {
        return $this->maxHealth;
    }

    /**
     * Returns a list of buffs this creature might have.
     * @return BuffList
     */
    public function getBuffs(): BuffList
    {
        if (empty($this->bufflist)) {
            $this->bufflist = new BuffList(new ArrayCollection());
        }
        return $this->bufflist;
    }

    /**
     * Sets the weapon name.
     * @param string $weapon
     */
    public function setWeapon(string $weapon)
    {
        $this->weapon = $weapon;
    }

    /**
     * Returns the weapon name.
     * @return string
     */
    public function getWeapon(): string
    {
        return $this->weapon;
    }

    /**
     * Returns the experience earned through this monster with character scaling.
     * @param Character $character
     * @param float $bonusFactor
     * @param float $malusFactor
     * @return array
     */
    #[ArrayShape(['int', 'float'])]
    public function getExperience(
        Character $character,
        float $bonusFactor = 0,
        float $malusFactor = 0,
    ): array {
        $levelDifference = $this->level - $character->getLevel();

        // Get default experience from a simple table lookup.
        // ToDo: Make this configurable per creature
        if (isset(self::ExperienceTable[$this->level])) {
            $experience = self::ExperienceTable[$this->level];
        } else {
            $experience = 0;
        }

        // Reward fights against stronger monsters, punish for slaying weaker ones.
        $bonusExperience = 0;
        if ($levelDifference < 0) {
            // Because levelDifference is negative, bonusExperience is negative, too.
            $bonusExperience = $experience * $malusFactor * $levelDifference;
        } elseif ($levelDifference > 0) {
            // Because levelDifference is positive, bonusExperience will be positive, too.
            $bonusExperience = $experience * $bonusFactor * $levelDifference;
        }

        $bonusExperience = (int)round($bonusExperience, 0);

        // Calculate the actual amount of experience earned.
        $experience = $experience + $bonusExperience;

        if ($experience <= 0) {
            $experience = 1;
        }

        return [$experience, $bonusExperience];
    }
}