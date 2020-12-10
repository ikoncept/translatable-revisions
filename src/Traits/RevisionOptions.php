<?php

namespace Infab\TranslatableRevisions\Traits;

class RevisionOptions {

    /**
     * Types that require special care,
     * for example files, images etc
     * @var array $specialTypes
     */
    public $specialTypes = [];

    /**
     * Getters, specify how the fields should
     * be represented or transformed
     * @var array $getters
     */
    public $getters = [];


    /**
     * Default included getters
     * @var array $getters
     */
    public $defaultGetters = ['repeater' => 'getRepeater'];

    public static function create(): self
    {
        return new static();
    }


    /**
     * Register affected types
     *
     * @param array $types
     * @return self
     */
    public function registerSpecialTypes(array $types): self
    {
        $this->specialTypes = array_merge($types, $this->defaultGetters);

        return $this;
    }

    /**
     * Register new getters
     *
     * @param array $getters
     * @return self
     */
    public function registerGetters(array $getters): self
    {
        $this->getters = $getters;

        return $this;
    }
}