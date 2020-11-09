<?php

namespace Infab\PageModule\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Infab\PageModule\Models\PageTemplate;
use Illuminate\Support\Str;

class PageTemplateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PageTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'slug' => Str::slug($this->faker->name)
        ];
    }
}
