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

    public function setName(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setLevel(int $level)
    {
        $this->level = $level;
    }

    public function setAttack(int $attack)
    {
        $this->attack = $attack;
    }

    public function getAttack(Game $game, bool $ignoreBuffs = false): int
    {
        return $this->attack;
    }

    public function setDefense(int $defense)
    {
        $this->defense = $defense;
    }

    public function getDefense(Game $game, bool $ignoreBuffs = false): int
    {
        return $this->defense;
    }

    public function setMaxHealth(int $maxHealth): void
    {
        $this->maxHealth = $maxHealth;
    }

    public function getMaxHealth(): int
    {
        return $this->maxHealth;
    }

    public function getBuffs(): BuffList
    {
        if (empty($this->bufflist)) {
            $this->bufflist = new BuffList(new ArrayCollection());
        }
        return $this->bufflist;
    }

    public function setWeapon(string $weapon)
    {
        $this->weapon = $weapon;
    }

    public function getWeapon(): string
    {
        return $this->weapon;
    }
}