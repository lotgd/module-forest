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
    protected string $weapon;
    /** @Column(type="integer") */
    protected int $attack;
    /** @Column(type="integer") */
    protected int $defense;
    /** @Column(type="integer") */
    protected int $maxHealth;
    /** @Column(type="integer", nullable=True, options={"unsigned": true}) */
    protected ?int $gold = null;
    /** @Column(type="integer", nullable=True, options={"unsigned": true}) */
    protected ?int $experience = null;

    protected ?BuffList $bufflist = null;

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

    const GoldTable = [
        1 => 36,
        2 => 97,
        3 => 148,
        4 => 162,
        5 => 198,
        6 => 234,
        7 => 268,
        8 => 302,
        9 => 336,
        10 => 369,
        11 => 402,
        12 => 435,
        13 => 467,
        14 => 499,
        15 => 531,
        16 => 563,
        17 => 36,
        18 => 0,
    ];

    public function __construct(
        string $name,
        string $weapon,
        int $level = 1,
        int $attack = 1,
        int $defense = 1,
        int $maxHealth = 10,
        int $experience = null,
        int $gold = null,
    ) {
        parent::__construct();

        $this->name = $name;
        $this->level = $level;
        $this->weapon = $weapon;
        $this->attack = $attack;
        $this->defense = $defense;
        $this->maxHealth = $maxHealth;
        $this->health = $maxHealth;
        $this->experience = $experience;
        $this->gold = $gold;
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
    public function getScaledExperience(
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

    /**
     * Returns the raw base experience.
     * @return int
     */
    public function getExperience(): int
    {
        return $this->experience ?? (isset(self::ExperienceTable[$this->level]) ? self::ExperienceTable[$this->level] : 0);
    }

    /**
     * Sets the experience gained from this creature.
     * @param int|null $experience
     */
    public function setExperience(?int $experience): void
    {
        $this->experience = $experience;
    }

    /**
     * Returns the amount of gold dropped by this enemy.
     * @return int
     */
    public function getGold(): int
    {
        return $this->gold ?? (isset(self::GoldTable[$this->level]) ? self::GoldTable[$this->level] : 0);
    }

    /**
     * Sets the amount of gold dropped by this enemy. Set to null to use default scaling.
     * @param int|null $gold
     */
    public function setGold(?int $gold)
    {
        $this->gold = $gold;
    }
}