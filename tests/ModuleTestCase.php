<?php
declare(strict_types=1);

namespace LotGD\Module\Forest\Tests;

use Doctrine\Common\Annotations\AnnotationRegistry;
use LotGD\Core\Action;
use LotGD\Core\Exceptions\ArgumentException;
use LotGD\Core\GameBuilder;
use LotGD\Core\LibraryConfigurationManager;
use LotGD\Core\ModelExtender;
use LotGD\Core\Models\Viewpoint;
use Monolog\Logger;
use Monolog\Handler\NullHandler;

use LotGD\Core\Configuration;
use LotGD\Core\Game;
use LotGD\Core\Tests\ModelTestCase;
use LotGD\Core\Models\Module as ModuleModel;

use LotGD\Module\Forest\Module;
use PHPUnit\Framework\AssertionFailedError;

class ModuleTestCase extends ModelTestCase
{
    const Library = 'lotgd/module-forest';
    const RootNamespace = "LotGD\\Module\\Forest\\";

    public $g;
    protected $moduleModel;

    protected function getDataSet(): \PHPUnit_Extensions_Database_DataSet_YamlDataSet
    {
        return new \PHPUnit_Extensions_Database_DataSet_YamlDataSet(implode(DIRECTORY_SEPARATOR, [__DIR__, 'datasets', $this->dataset . '.yml']));
    }

    public function setUp()
    {
        parent::setUp();

        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();

        // Make an empty logger for these tests. Feel free to change this
        // to place log messages somewhere you can easily find them.
        $logger  = new Logger('test');
        $logger->pushHandler(new NullHandler());

        // Create a Game object for use in these tests.
        $this->g = (new GameBuilder())
            ->withConfiguration(new Configuration(getenv('LOTGD_TESTS_CONFIG_PATH')))
            ->withLogger($logger)
            ->withEntityManager($this->getEntityManager())
            ->withCwd(implode(DIRECTORY_SEPARATOR, [__DIR__, '..']))
            ->create();

        // Register and unregister before/after each test, since
        // handleEvent() calls may expect the module be registered (for example,
        // if they read properties from the model).
        $this->moduleModel = new ModuleModel(self::Library);
        $this->moduleModel->save($this->getEntityManager());
        Module::onRegister($this->g, $this->moduleModel);

        $this->g->getEntityManager()->flush();
        $this->g->getEntityManager()->clear();

        // Run model extender
        AnnotationRegistry::registerLoader("class_exists");

        $modelExtender = new ModelExtender();
        $libraryConfigurationManager = new LibraryConfigurationManager($this->g->getComposerManager(), getcwd());

        foreach ($libraryConfigurationManager->getConfigurations() as $config) {
            $modelExtensions = $config->getSubKeyIfItExists(["modelExtensions"]);

            if ($modelExtensions) {
                $modelExtender->addMore($modelExtensions);
            }
        }

        $this->g->getEntityManager()->flush();
        $this->g->getEntityManager()->clear();
    }

    public function tearDown()
    {
        $this->g->getEntityManager()->flush();
        $this->g->getEntityManager()->clear();

        parent::tearDown();

        Module::onUnregister($this->g, $this->moduleModel);
        $m = $this->getEntityManager()->getRepository(ModuleModel::class)->find(self::Library);
        if ($m) {
            $m->delete($this->getEntityManager());
        }
    }

    protected function takeActions(Game $game, Viewpoint $viewpoint, array $actions)
    {
        foreach ($actions as $action) {
            if (is_int($action)) {
                $searchFor = "getDestinationSceneId";
            } elseif (is_string($action)) {
                $searchFor = "getTitle";
            } else {
                throw new \Exception("\$actions parameter must be a list containing integers or strings.");
            }

            foreach ($viewpoint->getActionGroups() as $group) {
                foreach ($group->getActions() as $a) {
                    if ($a->$searchFor() == $action) {
                        $game->takeAction($a->getId());
                        break 2;
                    }
                }
            }
        }
    }

    protected function searchAction(Viewpoint $viewpoint, array $actionParams, ?string $groupTitle = null): ?Action
    {
        if (count($actionParams) != 2) {
            throw new ArgumentException("$actionParams is expected to be an array of exactly 2 items.");
        }

        if (is_string($actionParams[0]) === false) {
            throw new ArgumentException("$actionParams[0] is expected to be a method.");
        }

        $methodToCheck = $actionParams[0];
        $valueToHave = $actionParams[1];
        $checkedOnce = false;


        $groups = $viewpoint->getActionGroups();
        $found = null;

        foreach ($groups as $group) {
            $actions = $group->getActions();
            foreach ($actions as $action) {
                if ($checkedOnce === false and method_exists($action, $methodToCheck) === false) {
                    throw new ArgumentException("$actionParams[0] must be a valid method of " . Action::class . ".");
                } else {
                    $checkedOnce = True;
                }

                # Using KNF, !A or B is only false if A is true and B is not.
                if ($action->$methodToCheck() == $valueToHave and (!is_null($groupTitle) or $group->getTitle() === $groupTitle)) {
                    $found = $action;
                }
            }
        }

        return $found;
    }

    protected function assertNotHasAction(Viewpoint $viewpoint, array $actionParams, ?string $groupTitle = null): void
    {
        $action = $this->searchAction($viewpoint, $actionParams, $groupTitle);

        if ($action !== null) {
            throw new AssertionFailedError("Assertion that viewpoint has not an action failed.");
        }
    }

    protected function assertHasAction(Viewpoint $viewpoint, array $actionParams, ?string $groupTitle = null): Action
    {
        $action = $this->searchAction($viewpoint, $actionParams, $groupTitle);

        if ($action === null) {
            throw new AssertionFailedError("Assertion that viewpoint has action failed.");
        }

        return $action;
    }
}