<?php

namespace Infab\TranslatableRevisions;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;

class TranslatableRevisionsServiceProvider extends ServiceProvider
{
    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/translatable-revisions.php' => config_path('translatable-revisions.php'),
        ], 'config');

        // Migrations
        $this->publishes([
            __DIR__.'/../database/migrations/create_translatable_revisions_table.php.stub' => $this->getMigrationFileName('create_translatable_revisions_table.php'),
        ], 'migrations');

        $this->publishes([
            __DIR__.'/../database/migrations/create_translatable_revision_meta_table.php.stub' => $this->getMigrationFileName('create_translatable_revision_meta_table.php'),
        ], 'migrations');

        $this->publishes([
            __DIR__.'/../database/migrations/create_translatable_revision_templates_table.php.stub' => $this->getMigrationFileName('create_translatable_revision_templates_table.php'),
        ], 'migrations');

        $this->publishes([
            __DIR__.'/../database/migrations/create_translatable_revision_template_fields_table.php.stub' => $this->getMigrationFileName('create_translatable_revision_template_fields_table.php'),
        ], 'migrations');

        $this->publishes([
            __DIR__.'/../database/migrations/create_i18n_tables.php.stub' => $this->getMigrationFileName('create_i18n_tables.php'),
        ], 'migrations');

        // if ($this->app->runningInConsole()) {
        //     if (! class_exists('CreateTranslatableRevisionMetaTable')) {
        //         $this->publishes([
        //             __DIR__.'/../database/migrations/create_translatable_revision_meta_table.php.stub' => database_path('migrations/'.$date.'_create_translatable_revision_meta_table.php'),
        //         ], 'migrations');
        //     }
        // }

        // // Third
        // $date = now()->addSeconds(4)->format('Y_m_d_His');
        // if (! class_exists('CreateTranslatableRevisionTemplatesTable')) {
        //     $this->publishes([
        //         __DIR__.'/../database/migrations/create_translatable_revision_templates_table.php.stub' => database_path('migrations/'.$date.'_create_translatable_revision_templates_table.php'),
        //     ], 'migrations');
        // }

        // // Fourth
        // $date = now()->addSeconds(6)->format('Y_m_d_His');
        // $this->publishes([
        //     __DIR__.'/../database/migrations/create_translatable_revision_template_fields_table.php.stub' => database_path('migrations/'.$date.'_create_translatable_revision_template_fields_table.php'),
        // ], 'migrations');

        // // Fifth
        // $date = now()->addSeconds(8)->format('Y_m_d_His');
        // $this->publishes([
        //     __DIR__.'/../database/migrations/create_i18n_tables.php.stub' => database_path('migrations/'.$date.'_create_i18n_tables.php'),
        // ], 'migrations');
    }

    /**
     * Returns existing migration file if found, else uses the current timestamp.
     *
     * @return string
     */
    protected function getMigrationFileName(string $migrationFileName): string
    {
        $timestamp = date('Y_m_d_His');

        $filesystem = $this->app->make(Filesystem::class);

        return Collection::make($this->app->databasePath().DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR)
            ->flatMap(function ($path) use ($filesystem, $migrationFileName) {
                return $filesystem->glob($path.'*_'.$migrationFileName);
            })
            ->push($this->app->databasePath()."/migrations/{$timestamp}_{$migrationFileName}")
            ->first();
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/translatable-revisions.php', 'translatable-revisions');
    }
}
