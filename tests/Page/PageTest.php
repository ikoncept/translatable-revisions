<?php

namespace Infab\TranslatableRevisions\Tests\Page;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Infab\TranslatableRevisions\Models\Page;
use Infab\TranslatableRevisions\Models\RevisionMeta;
use Infab\TranslatableRevisions\Models\RevisionTemplate;
use Infab\TranslatableRevisions\Models\RevisionTemplateField;
use Infab\TranslatableRevisions\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Infab\TranslatableRevisions\Events\DefinitionsPublished;
use Infab\TranslatableRevisions\Events\DefinitionsUpdated;
use Prophecy\Prophecy\Revealer;

class PageTest extends TestCase
{
    use RefreshDatabase;

    /** @test **/
    public function it_can_get_a_representation_of_a_page_model()
    {
        $pageModel = new Page();

        $this->assertInstanceOf(Page::class, $pageModel);
    }

    /** @test **/
    public function it_can_set_the_pages_table_via_config_file()
    {
        $page = new Page();
        $table = $page->getTable();

        $this->assertEquals('pages', $table);

        Config::set('translatable-revisions.revisions_table_name', 'new_pages');
        $this->assertEquals('new_pages', config('translatable-revisions.revisions_table_name'));
    }

    /** @test **/
    public function it_can_have_a_page_template()
    {
        // Arrange
        $template = RevisionTemplate::factory()->create();
        $page = Page::factory()->create([
            'template_id' => $template->id
        ]);

        // Act
        $this->assertInstanceOf(RevisionTemplate::class, $page->template);
    }

    /** @test **/
    public function it_can_have_meta_data()
    {
        // Arrange
        $page = Page::factory()->create();
        $meta_data = RevisionMeta::factory()->count(2)->create();
        $page->meta()->save($meta_data->first());
        $page->meta()->save($meta_data->last());

        // Assert
        $this->assertInstanceOf(RevisionMeta::class, $page->meta->first());
        $this->assertCount(2, $page->meta);
        $this->assertArrayHasKey('meta_key', $meta_data->first()->toArray());
        $this->assertEquals('Infab\TranslatableRevisions\Models\Page', $meta_data->first()->model_type);
    }

    /** @test **/
    public function it_can_update_fields_for_a_page()
    {
        // Arrange
        $template = RevisionTemplate::factory()->create();
        $templateFields = RevisionTemplateField::factory()->count(5)->create([
            'template_id' => $template->id,
            'translated' => true
        ]);
        $page = Page::factory()->create([
            'template_id' => $template->id,
            'revision' => 10
        ]);

        // Act
        $key = $templateFields->first()->toArray()['key'];
        $fields = $page->updateContent([
            $key => 'En hel del saker'
        ], 10, 'sv');

        // Assert
        $this->assertDatabaseHas('i18n_definitions', [
            'content' => 'En hel del saker',
            'locale' => 'sv'
        ]);
        $this->assertDatabaseHas('i18n_terms', [
            'key' => 'page_' . $page->id .'_'. $page->revision . '_' . $key,
            'description' => $templateFields->first()->name . ' for ' . $page->title
        ]);
    }

    /** @test **/
    public function it_can_update_grouped_fields_for_a_page()
    {
        // Arrange
        $template = RevisionTemplate::factory()->create();
        $titleField = RevisionTemplateField::factory()->create([
            'template_id' => $template->id,
            'name' => 'Page title',
            'translated' => true,
            'key' => 'page_title'
        ]);
        $boxField = RevisionTemplateField::factory()->create([
            'template_id' => $template->id,
            'translated' => true,
            'key' => 'boxes',
            'name' => 'Boxes',
            'repeater' => true
        ]);
        $page = Page::factory()->create([
            'template_id' => $template->id,
            'revision' => 10
        ]);

        // Act
        $fields = $page->updateContent([
            'page_title' => 'The page title for the page',
            'boxes' => [
                ['title' => 'Box 1 title!', 'url' => 'https://google.com'],
                ['title' => 'Box 2 title!', 'url' => 'https://bog.com'],
                ['title' => 'Box 3 title!', 'url' => 'http://flank.se'],
            ]
        ], 10);

        // Assert
        $this->assertDatabaseHas('i18n_definitions', [
            'content' => 'The page title for the page'
        ]);
        $this->assertDatabaseHas('i18n_definitions', [
            'content' => 'The page title for the page'
        ]);

        $this->assertDatabaseHas('i18n_terms', [
            'key' => 'page_' . $page->id .'_'. $page->revision . '_page_title',
        ]);
        $this->assertDatabaseHas('i18n_terms', [
            'key' => 'page_' . $page->id .'_'. $page->revision . '_boxes__0_url',
        ]);
        $this->assertDatabaseHas('i18n_terms', [
            'key' => 'page_' . $page->id .'_'. $page->revision . '_boxes__1_url',
        ]);
        $this->assertDatabaseHas('i18n_terms', [
            'key' => 'page_' . $page->id .'_'. $page->revision . '_boxes__2_url',
        ]);

        $this->assertDatabaseHas('i18n_terms', [
            'key' => 'page_' . $page->id .'_'. $page->revision . '_page_title',
        ]);
        $this->assertDatabaseHas('i18n_terms', [
            'key' => 'page_' . $page->id .'_'. $page->revision . '_boxes__0_title',
        ]);
        $this->assertDatabaseHas('i18n_terms', [
            'key' => 'page_' . $page->id .'_'. $page->revision . '_boxes__1_title',
        ]);
        $this->assertDatabaseHas('i18n_terms', [
            'key' => 'page_' . $page->id .'_'. $page->revision . '_boxes__2_title',
        ]);



        $this->assertDatabaseHas('i18n_definitions', [
            'locale' => 'en',
            'content' => 'https://google.com',
        ]);
        $this->assertDatabaseHas('i18n_definitions', [
            'locale' => 'en',
            'content' => 'https://bog.com',
        ]);
        $this->assertDatabaseHas('i18n_definitions', [
            'locale' => 'en',
            'content' => 'http://flank.se',
        ]);
    }

    /** @test **/
    public function it_can_publish_a_revision()
    {
        // Arrange
        Event::fake(DefinitionsPublished::class);
        $prefix = config('translatable-revisions.i18n_table_prefix_name');
        DB::table($prefix . 'i18n_locales')->insert([
            'name' => 'Swedish',
            'native' => 'Svenska',
            'iso_code' => 'sv',
            'regional' => 'se_SV',
            'enabled' => true
        ]);
        $template = RevisionTemplate::factory()->create();
        $templateFields = RevisionTemplateField::factory()->count(5)->create([
            'template_id' => $template->id,
            'translated' => true
        ]);
        $page = Page::factory()->create([
            'id' => 1,
            'title' => 'Original start page',
            'template_id' => $template->id,
            'revision' => 1,
            'published_version' => null
        ]);
        $key = $templateFields->first()->toArray()['key'];

        $fields = $page->updateContent([
            $key => 'En hel del saker'
        ], 1, 'sv');

        $revisionFields = $page->updateContent([
            $key => 'What, helt annat'
        ], 2, 'sv');

        $enFields = $page->updateContent([
            $key => 'A bunch of things'
        ], 1, 'en');

        $enRevisionFields = $page->updateContent([
            $key => 'What, something completeley different!'
        ], 2, 'en');


        // Act
        $page->publish(2);

        // Assert
        $this->assertDatabaseHas('pages', [
            'id' => 1,
            'published_version' => 2,
            'revision' => 3
        ]);

        $this->assertDatabaseMissing('i18n_definitions', [
            'content' => 'En hel del saker',
            'locale' => 'sv'
        ]);
        $this->assertDatabaseHas('i18n_definitions', [
            'content' => 'What, helt annat',
            'locale' => 'sv'
        ]);

        $this->assertDatabaseMissing('i18n_definitions', [
            'content' => 'A bunch of things',
            'locale' => 'en'
        ]);
        $this->assertDatabaseHas('i18n_definitions', [
            'content' => 'What, something completeley different!',
            'locale' => 'en'
        ]);

        Event::assertDispatched(DefinitionsPublished::class);
    }

    /** @test **/
    public function it_can_get_field_content()
    {
        // Arrange
        Event::fake(DefinitionsUpdated::class);
        $template = RevisionTemplate::factory()->create();
        $titleField = RevisionTemplateField::factory()->create([
            'template_id' => $template->id,
            'name' => 'Page title',
            'translated' => true,
            'key' => 'page_title'
        ]);
        $boxField = RevisionTemplateField::factory()->create([
            'template_id' => $template->id,
            'translated' => true,
            'key' => 'boxes',
            'name' => 'Boxes',
            'repeater' => true,
            'type' => 'grid'
        ]);
        $page = Page::factory()->create([
            'template_id' => $template->id,
            'revision' => 10
        ]);

        // Act
        app()->setLocale('sv');
        $fields = $page->updateContent([
            'page_title' => 'The page title for the page',
            'boxes' => [
                ['title' => 'Box 1 title!', 'url' => 'https://google.com'],
                ['title' => 'Box 2 title!', 'url' => 'https://bog.com'],
                ['title' => 'Box 3 title!', 'url' => 'http://flank.se'],
            ]
        ], 10);

        // Act
        $content = $page->getFieldContent(10);

        // Assert
        $this->assertEquals(3, count($content['boxes']));
        Event::assertDispatched(DefinitionsUpdated::class);
    }
}
