# module-forest
[![Build Status](https://travis-ci.org/lotgd/module-forest.svg?branch=master)](https://travis-ci.org/lotgd/module-forest)

This module provides a forest and a healer's hut where characters can engage in combat in a 
varying degree of difficulties.

## API
### Events and hooks
- `h/lotgd/module-forest/forest-navigation` (`Module::HookForestNavigation`)\
This hook can be used to extend the forest navigation. The only variable it provides is the viewpoint.
