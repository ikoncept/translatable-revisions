<?php

namespace Infab\PageModule\Tests;

use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    public function setUp() : void
    {
        parent::setUp();

        // Pass::routes(function ($registrar) { $registrar->forAuthorization(); });
        $this->loadLaravelMigrations(['--database' => 'testing']);
        $this->setUpDatabase($this->app);
    }

    public function setUpDatabase($app)
    {
        $app['config']->set('page-module.pages_table_name', 'pages');
        $app['config']->set('page-module.page_templates_table_name', 'page_templates');
        $app['config']->set('page-module.page_meta_table_name', 'page_meta');


        include_once(__DIR__  . '/../database/migrations/create_pages_table.php.stub');
        (new \CreatePagesTable())->up();

        include_once(__DIR__  . '/../database/migrations/create_page_templates_table.php.stub');
        (new \CreatePageTemplatesTable())->up();

        include_once(__DIR__  . '/../database/migrations/create_page_meta_table.php.stub');
        (new \CreatePageMetaTable())->up();
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}