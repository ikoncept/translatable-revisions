<?php

namespace Infab\PageModule\Tests\Page;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Infab\PageModule\Models\Page;
use Infab\PageModule\Models\PageMeta;
use Infab\PageModule\Models\PageTemplate;
use Infab\PageModule\Models\PageTemplateField;
use Infab\PageModule\Tests\TestCase;

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

        Config::set('page-module.pages_table_name', 'new_pages');
        $this->assertEquals('new_pages', config('page-module.pages_table_name'));
    }

    /** @test **/
    public function it_can_have_a_page_template()
    {
        // Arrange
        $template = PageTemplate::factory()->create();
        $page = Page::factory()->create([
            'template_id' => $template->id
        ]);

        // Act
        $this->assertInstanceOf(PageTemplate::class, $page->template);
    }

    /** @test **/
    public function it_can_have_meta_data()
    {
        // Arrange
        $page = Page::factory()->create();
        $meta_data = PageMeta::factory()->count(2)->create([
            'page_id' => $page->id
        ]);

        // Assert
        $this->assertInstanceOf(PageMeta::class, $page->meta->first());
        $this->assertCount(2, $page->meta);
        $this->assertArrayHasKey('meta_key', $meta_data->first()->toArray());
    }

    /** @test **/
    public function it_can_update_fields_for_a_page()
    {
        // Arrange
        $template = PageTemplate::factory()->create();
        $templateFields = PageTemplateField::factory()->count(5)->create([
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
        ], 'sv', 10);

        // Assert
        $this->assertDatabaseHas('i18n_definitions', [
            'content' => 'En hel del saker'
        ]);
        $this->assertDatabaseHas('i18n_terms', [
            'key' => 'page_' . $page->id .'_'. $page->revision . '_' . $key,
            'description' => $templateFields->first()->name . ' for ' . $page->title
        ]);
    }

    /** @test **/
    public function it_can_publish_a_revision()
    {
        // Arrange
        $template = PageTemplate::factory()->create();
        $templateFields = PageTemplateField::factory()->count(5)->create([
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
        ], 'sv', 1);

        $revisionFields = $page->updateContent([
            $key => 'What, helt annat'
        ], 'sv', 2);

        // Act
        $page->publish(2);

        // Assert
        $this->assertDatabaseHas('pages', [
            'id' => 1,
            // 'title' => 'Startsidan - försök två',
            'published_version' => 2,
            'revision' => 3
        ]);

        $this->assertDatabaseMissing('i18n_definitions', [
            'content' => 'En hel del saker'
        ]);
        $this->assertDatabaseHas('i18n_definitions', [
            'content' => 'What, helt annat'
        ]);
    }
}
