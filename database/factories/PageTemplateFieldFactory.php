<?php

namespace Infab\PageModule\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Infab\PageModule\Models\Page;
use Infab\PageModule\Models\PageTemplateField;
use Illuminate\Support\Str;
use Infab\PageModule\Models\PageTemplate;

class PageTemplateFieldFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PageTemplateField::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'template_id' => PageTemplate::factory()->create()->id,
            'key' => $this->faker->randomElement(['page_header', 'page_title', 'page_thing', 'page_aroo', 'widget_title', 'widget_body', 'page_main_content']),
            'name' => $this->faker->domainName,
            'type' => $this->faker->randomElement(['text', 'html', 'image']),
        ];
    }
}
