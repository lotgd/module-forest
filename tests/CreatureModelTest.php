<?php
declare(strict_types=1);

namespace LotGD\Module\Forest\Tests;

use LotGD\Core\Models\Character;
use LotGD\Module\Forest\Models\Creature;

class CreatureModelTest extends ModuleTestCase
{
    public function experienceTestProvider() {
        return [
            [1, new Creature(name: "Test", weapon: "Test", level: 1), Creature::ExperienceTable[1]],
            [2, new Creature(name: "Test", weapon: "Test", level: 2), Creature::ExperienceTable[2]],
            [3, new Creature(name: "Test", weapon: "Test", level: 3), Creature::ExperienceTable[3]],
            [4, new Creature(name: "Test", weapon: "Test", level: 4), Creature::ExperienceTable[4]],
            [5, new Creature(name: "Test", weapon: "Test", level: 5), Creature::ExperienceTable[5]],
            [6, new Creature(name: "Test", weapon: "Test", level: 6), Creature::ExperienceTable[6]],
            [7, new Creature(name: "Test", weapon: "Test", level: 7), Creature::ExperienceTable[7]],
            [8, new Creature(name: "Test", weapon: "Test", level: 8), Creature::ExperienceTable[8]],
            [9, new Creature(name: "Test", weapon: "Test", level: 9), Creature::ExperienceTable[9]],
            [10, new Creature(name: "Test", weapon: "Test", level: 10), Creature::ExperienceTable[10]],
            [11, new Creature(name: "Test", weapon: "Test", level: 11), Creature::ExperienceTable[11]],
            [12, new Creature(name: "Test", weapon: "Test", level: 12), Creature::ExperienceTable[12]],
            [13, new Creature(name: "Test", weapon: "Test", level: 13), Creature::ExperienceTable[13]],
            [14, new Creature(name: "Test", weapon: "Test", level: 14), Creature::ExperienceTable[14]],
            [15, new Creature(name: "Test", weapon: "Test", level: 15), Creature::ExperienceTable[15]],
        ];
    }

    /**
     * @dataProvider experienceTestProvider
     */
    public function testIfCreatureGetExperienceReturnsExpectedExperienceValuesWithEqualLevels(
        int $level,
        Creature $creature,
        int $expectedExperience,
    ) {
        $character = Character::createAtFullHealth(["name" => "Char", "level" => $level, "maxHealth" => 10]);

        [$experienceRewarded, $bonusExperience] = $creature->getScaledExperience($character);
        $this->assertSame($expectedExperience, $experienceRewarded);
        $this->assertSame(0, $bonusExperience);
    }

    /**
     * @dataProvider experienceTestProvider
     */
    public function testIfCreatureGetExperienceReturnsExpectedExperienceValuesWithEqualLevelsAndBonus(
        int $level,
        Creature $creature,
        int $expectedExperience,
    ) {
        $character = Character::createAtFullHealth(["name" => "Char", "level" => $level, "maxHealth" => 10]);

        // The bonusFactor should not influence the experience gained, since the levels are equal
        [$experienceRewarded, $bonusExperience] = $creature->getScaledExperience($character, bonusFactor: 1);
        $this->assertSame($expectedExperience, $experienceRewarded);
        $this->assertSame(0, $bonusExperience);
    }

    /**
     * @dataProvider experienceTestProvider
     */
    public function testIfCreatureGetExperienceReturnsExpectedExperienceValuesWithEqualLevelsAndMalus(
        int $level,
        Creature $creature,
        int $expectedExperience,
    ) {
        $character = Character::createAtFullHealth(["name" => "Char", "level" => $level, "maxHealth" => 10]);

        // The malusFactor should not influence the experience gained, since the levels are equal
        [$experienceRewarded, $bonusExperience] = $creature->getScaledExperience($character, malusFactor: 1);
        $this->assertSame($expectedExperience, $experienceRewarded);
        $this->assertSame(0, $bonusExperience);
    }

    /**
     * @dataProvider experienceTestProvider
     */
    public function testIfCreatureGetExperienceReturnsExpectedExperienceValuesWithCharacterAtHigherLevel(
        int $level,
        Creature $creature,
        int $expectedExperience,
    ) {
        $character = Character::createAtFullHealth(["name" => "Char", "level" => $level+1, "maxHealth" => 10]);

        // bonus and malus are 0, nothing should happen
        [$experienceRewarded, $bonusExperience] = $creature->getScaledExperience($character);
        $this->assertSame($expectedExperience, $experienceRewarded);
        $this->assertSame(0, $bonusExperience);
    }

    /**
     * @dataProvider experienceTestProvider
     */
    public function testIfCreatureGetExperienceReturnsExpectedExperienceValuesWithCharacterAtHigherLevelAndBonus(
        int $level,
        Creature $creature,
        int $expectedExperience,
    ) {
        $character = Character::createAtFullHealth(["name" => "Char", "level" => $level+1, "maxHealth" => 10]);

        // bonus should not influence the result as the creature is weaker
        [$experienceRewarded, $bonusExperience] = $creature->getScaledExperience($character, bonusFactor: 1);
        $this->assertSame($expectedExperience, $experienceRewarded);
        $this->assertSame(0, $bonusExperience);
    }

    /**
     * @dataProvider experienceTestProvider
     */
    public function testIfCreatureGetExperienceReturnsExpectedExperienceValuesWithCharacterAtHigherLevelAndMalus(
        int $level,
        Creature $creature,
        int $expectedExperience,
    ) {
        $character = Character::createAtFullHealth(["name" => "Char", "level" => $level+1, "maxHealth" => 10]);

        // malus factor of 1 should set the experience to half
        [$experienceRewarded, $bonusExperience] = $creature->getScaledExperience($character, malusFactor: 0.5);

        $bonusExperienceExpected = -(int)round($expectedExperience * 0.5, 0);
        $expectedExperience = (int)round($expectedExperience * 0.5, 0, PHP_ROUND_HALF_DOWN);

        $this->assertSame($expectedExperience, $experienceRewarded);
        $this->assertSame($bonusExperienceExpected, $bonusExperience);
    }

    /**
     * @dataProvider experienceTestProvider
     */
    public function testIfCreatureGetExperienceReturnsExpectedExperienceValuesWithCharacterAtLowerLevel(
        int $level,
        Creature $creature,
        int $expectedExperience,
    ) {
        $character = Character::createAtFullHealth(["name" => "Char", "level" => $level-1, "maxHealth" => 10]);

        // bonus and malus are 0, nothing should happen
        [$experienceRewarded, $bonusExperience] = $creature->getScaledExperience($character);
        $this->assertSame($expectedExperience, $experienceRewarded);
        $this->assertSame(0, $bonusExperience);
    }

    /**
     * @dataProvider experienceTestProvider
     */
    public function testIfCreatureGetExperienceReturnsExpectedExperienceValuesWithCharacterAtLowerLevelAndBonus(
        int $level,
        Creature $creature,
        int $expectedExperience,
    ) {
        $character = Character::createAtFullHealth(["name" => "Char", "level" => $level-1, "maxHealth" => 10]);

        // bonus should should change the result, as the creature is stronger.
        [$experienceRewarded, $bonusExperience] = $creature->getScaledExperience($character, bonusFactor: 1);

        $this->assertSame(2*$expectedExperience, $experienceRewarded);
        $this->assertSame($expectedExperience, $bonusExperience);
    }

    /**
     * @dataProvider experienceTestProvider
     */
    public function testIfCreatureGetExperienceReturnsExpectedExperienceValuesWithCharacterAtLowerLevelAndMalus(
        int $level,
        Creature $creature,
        int $expectedExperience,
    ) {
        $character = Character::createAtFullHealth(["name" => "Char", "level" => $level-1, "maxHealth" => 10]);

        // A malus should do nothing, as the creature is stronger
        [$experienceRewarded, $bonusExperience] = $creature->getScaledExperience($character, malusFactor: 0.5);
        $this->assertSame($expectedExperience, $experienceRewarded);
        $this->assertSame(0, $bonusExperience);
    }
}