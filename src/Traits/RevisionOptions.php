<?php

namespace Infab\TranslatableRevisions\Traits;

class RevisionOptions {

    /**
     * Types that require special care,
     * for example files, images etc
     */
    public array $specialTypes = [];

    public array $getters = [];

    public array $defaultGetters = ['repeater' => 'getRepeater'];

    public static function create(): self
    {
        return new static();
    }


    public function registerSpecialTypes(array $types): self
    {
        $this->specialTypes = array_merge($types, $this->defaultGetters);

        return $this;
    }

    public function registerGetters(array $getters): self
    {
        $this->getters = $getters;

        return $this;
    }
}