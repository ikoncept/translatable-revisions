<?php

namespace Infab\TranslatableRevisions\Events;

use Illuminate\Support\Collection;

class DefinitionsPublished
{
    /**
     * The newly updated model.
     *
     */
    public $model;

    /**
     * The newly published definitions.
     *
     * @var
     */
    public $definitions;

    /**
     * Create a new event instance.
     *
     * @param $model
     * @return void
     */
    public function __construct(Collection $definitions, $model)
    {
        $this->definitions = $definitions;
        $this->model = $model;
    }
}