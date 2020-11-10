<?php

namespace Infab\PageModule;

use Illuminate\Support\ServiceProvider;

class PageModuleServiceProvider extends ServiceProvider
{
    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/page-module.php' => config_path('page-module.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/../database/migrations/create_pages_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()).'_create_pages_table.php'),
        ], 'migrations');

        $this->publishes([
            __DIR__.'/../database/migrations/create_page_templates_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()).'_create_page_templates_table.php'),
        ], 'migrations');

        $this->publishes([
            __DIR__.'/../database/migrations/create_page_meta_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()).'_create_page_meta_table.php'),
        ], 'migrations');

        $this->publishes([
            __DIR__.'/../database/migrations/create_page_template_fields_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()).'_create_page_template_fields_table.php'),
        ], 'migrations');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/config/page-module.php', 'page-module');
    }
}
