<?php

namespace Infab\PageModule\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Infab\PageModule\Models\Page;
use Infab\PageModule\Models\PageTemplate;

class PageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Page::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'title' => $this->faker->words(3, true),
            'template_id' => PageTemplate::factory()->create()->id
        ];
    }
}
