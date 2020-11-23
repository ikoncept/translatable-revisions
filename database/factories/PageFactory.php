<?php

namespace Infab\TranslatableRevisions\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Infab\TranslatableRevisions\Models\Page;
use Infab\TranslatableRevisions\Models\RevisionTemplate;

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
            'template_id' => RevisionTemplate::factory()->create()->id
        ];
    }
}
