# Forest module
![Tests](https://github.com/lotgd/module-forest/workflows/Tests/badge.svg)

This module provides a forest scene template for characters to engane in combat
in varying degrees of difficulties. It also provides a Healer's Hut, where characters
can heal themselves.

## API
### Events and hooks
- `h/lotgd/module-forest/forest-navigation` (`Module::HookForestNavigation`)\
  This hook can be used to extend the forest navigation. The only variable it provides is the viewpoint.
  
### Module Settings

- `experienceBonus = 0.25` (`Module::LostExperienceUponDeathProperty`)
  The amount of experience added to the base amount. 
  Should be between 0 and 1. Per level. 
  `exp = exp + exp*experienceBonus*levelDifference`
  
- `experienceMalus = 0.25` (`Module::ExperienceMalusFactorProperty`)
  The amount of experience subtracted from the base amount. 
  Should be higher than 0 and must be smaller or equal to 1. Per level. 
  `exp = exp - exp*experienceMalus*levelDifference`
  
- `gemDropProbability = 0.04` (`Module::GemDropProbabilityProperty`)
  The probability to drop a gem after a battle. 
  Must be between 0 and 1. 
  If its 0, it will never reward a gem, if its 1, every battle will reward a gem.
  Can be overwritten per scene.
  
- `lostExperienceUponDeath = 0.1` (`Module::LostExperienceUponDeathProperty`)
  Amount of experience that gets subtracted upon death, as a factor of the current experience.
  Must be between 0 and 1.
  If its 0, no experience will be lost. If its 1, the character loses all of their experience.
  `currentExperience = currentExperience * (1 - lostExperienceUponDeath)`
  Can be overwritten per scene.
  
