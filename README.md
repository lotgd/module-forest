# Forest module
![Tests](https://github.com/lotgd/module-forest/workflows/Tests/badge.svg)

This module provides a forest scene template for characters to engane in combat
in varying degrees of difficulties. It also provides a Healer's Hut, where characters
can heal themselves.

## API
### Events and hooks
- `h/lotgd/module-forest/forest-navigation` (`Module::HookForestNavigation`)\
  This hook can be used to extend the forest navigation. The only variable it provides is the viewpoint.
