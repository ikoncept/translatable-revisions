<?php

namespace Infab\TranslatableRevisions\Events;

use Illuminate\Support\Collection;

class DefinitionsUpdated
{
    /**
     * The newly updated definitions.
     *
     * @var
     */
    public $definitions;

    /**
     * The newly updated model.
     *
     */
    public $model;

    /**
     * Create a new event instance.
     *
     * @param Collection $definitions
     * @param $model
     * @return void
     */
    public function __construct(Collection $definitions, $model)
    {
        $this->definitions = $definitions;
        $this->model = $model;
    }
}