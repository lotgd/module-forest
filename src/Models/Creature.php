<?php
declare(strict_types=1);

namespace LotGD\Module\Forest\Models;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Table;
use LotGD\Core\BuffList;
use LotGD\Core\Game;
use LotGD\Core\Models\BasicEnemy;
use LotGD\Core\Models\CreateableInterface;
use LotGD\Core\Tools\Model\Creator;
use LotGD\Core\Tools\Model\Deletor;

/**
 * Class Creature
 * @package LotGD\Module\Forest\Models
 * @Entity
 * @Table(name="Creatures")
 */
class Creature extends BasicEnemy implements CreateableInterface
{
    use Creator;
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

    /**
     * @var array
     */
    private static $fillable = [
        "name",
        "weapon",
        "level",
        "attack",
        "defense",
        "maxHealth"
    ];

    public function __construct()
    {
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
     * @param Game $game
     * @param bool $ignoreBuffs
     * @return int
     */
    public function getAttack(Game $game, bool $ignoreBuffs = false): int
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
     * @param Game $game
     * @param bool $ignoreBuffs
     * @return int
     */
    public function getDefense(Game $game, bool $ignoreBuffs = false): int
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
}