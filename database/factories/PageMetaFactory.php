<?php

namespace Infab\PageModule\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Infab\PageModule\Models\Page;
use Infab\PageModule\Models\PageMeta;
use Illuminate\Support\Str;

class PageMetaFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = PageMeta::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'page_id' => Page::factory()->create()->id,
            'meta_key' => Str::slug($this->faker->words(3, true), '_'),
            'page_version' => random_int(1, 12),
            'meta_value' => json_encode([
                'title' => $this->faker->sentence,
                'content' => $this->faker->randomHtml(1, 1)
            ])
        ];
    }
}
