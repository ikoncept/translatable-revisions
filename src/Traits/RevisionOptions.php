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
     * Default template for a model which refers
     * to the slug field on the revision_templates table
     *
     *
     * @var string
     */
    public $defaultTemplate = '';


    /**
     * Default included getters
     * @var array $defaultGetters
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
        $this->specialTypes = $types;

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

    /**
     * Register a default template
     *
     * @param string $slug
     * @return self
     */
    public function registerDefaultTemplate(string $slug) : self
    {
        $this->defaultTemplate = $slug;

        return $this;
    }
}