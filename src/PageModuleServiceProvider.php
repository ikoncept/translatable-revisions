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


        // First
        $date = now()->format('Y_m_d_His');
        $this->publishes([
            __DIR__.'/../database/migrations/create_pages_table.php.stub' => database_path('migrations/'.$date.'_create_pages_table.php'),
        ], 'migrations');

        // Second
        $date = now()->addSeconds(2)->format('Y_m_d_His');
        $this->publishes([
            __DIR__.'/../database/migrations/create_page_meta_table.php.stub' => database_path('migrations/'.$date.'_create_page_meta_table.php'),
        ], 'migrations');

        // Third
        $date = now()->addSeconds(4)->format('Y_m_d_His');
        $this->publishes([
            __DIR__.'/../database/migrations/create_page_templates_table.php.stub' => database_path('migrations/'.$date.'_create_page_templates_table.php'),
        ], 'migrations');

        // Fourth
        $date = now()->addSeconds(6)->format('Y_m_d_His');
        $this->publishes([
            __DIR__.'/../database/migrations/create_page_template_fields_table.php.stub' => database_path('migrations/'.$date.'_create_page_template_fields_table.php'),
        ], 'migrations');

        // Fifth
        $date = now()->addSeconds(8)->format('Y_m_d_His');
        $this->publishes([
            __DIR__.'/../database/migrations/create_i18n_tables.php.stub' => database_path('migrations/'.$date.'_create_i18n_tables.php'),
        ], 'migrations');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/page-module.php', 'page-module');
    }
}
