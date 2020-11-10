<?php

namespace Infab\PageModule\Tests\Page;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Infab\PageModule\Models\Page;
use Infab\PageModule\Models\PageTemplate;
use Infab\PageModule\Models\PageTemplateField;
use Infab\PageModule\Tests\TestCase;

class PageTemplateTest extends TestCase
{
    use RefreshDatabase;

    /** @test **/
    public function it_can_get_a_representation_of_a_page_template_model()
    {
        $pageTemplate = new PageTemplate();

        $this->assertInstanceOf(PageTemplate::class, $pageTemplate);
    }

    /** @test **/
    public function a_template_can_have_many_template_fields()
    {
        // Arrange
        $template = PageTemplate::factory()->create();
        $templateFields = PageTemplateField::factory()->count(6)->create([
            'template_id' => $template->id
        ]);

        // Assert
        $this->assertCount(6, $template->fields);
    }
}
