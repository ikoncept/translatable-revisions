<?php

namespace Infab\TranslatableRevisions\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Infab\TranslatableRevisions\Models\RevisionTemplate;
use Illuminate\Support\Str;

class RevisionTemplateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = RevisionTemplate::class;

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
