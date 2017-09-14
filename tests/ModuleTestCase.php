<?php
declare(strict_types=1);

namespace LotGD\Module\Forest\Tests;

use LotGD\Core\GameBuilder;
use Monolog\Logger;
use Monolog\Handler\NullHandler;

use LotGD\Core\Configuration;
use LotGD\Core\Game;
use LotGD\Core\Tests\ModelTestCase;
use LotGD\Core\Models\Module as ModuleModel;

use LotGD\Module\Forest\Module;

class ModuleTestCase extends ModelTestCase
{
    const Library = 'lotgd/module-forest';
    const RootNamespace = "LotGD\\Module\\Forest\\";

    protected $g;
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
}