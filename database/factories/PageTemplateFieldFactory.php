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
            'name' => $this->faker->words(2, true),
            'key' => $this->faker->domainName,
            'type' => $this->faker->randomElement(['text', 'html', 'image']),
        ];
    }
}
