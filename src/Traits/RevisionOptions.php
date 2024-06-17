<?php

namespace Infab\TranslatableRevisions\Traits;

class RevisionOptions
{
    /**
     * Types that require special care,
     * for example files, images etc
     *
     * @var array
     */
    public $specialTypes = [];

    /**
     * Getters, specify how the fields should
     * be represented or transformed
     *
     * @var array
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
     * Arrayg of tags that should be
     * flushed on save and delete
     *
     * @var array
     */
    public $cacheTagsToFlush = [];

    /**
     * Default included getters
     *
     * @var array
     */
    public $defaultGetters = ['repeater' => 'getRepeater'];

    /**
     * If the model should be indexable
     *
     * @var bool
     */
    public $isIndexable = false;

    /**
     * Indexable keys
     *
     * @var array
     */
    public $indexableKeys = [];

    public static function create(): self
    {
        return new static();
    }

    /**
     * Register affected types
     */
    public function registerSpecialTypes(array $types): self
    {
        $this->specialTypes = $types;

        return $this;
    }

    /**
     * Register new getters
     */
    public function registerGetters(array $getters): self
    {
        $this->getters = $getters;

        return $this;
    }

    /**
     * Register a default template
     */
    public function registerDefaultTemplate(string $slug): self
    {
        $this->defaultTemplate = $slug;

        return $this;
    }

    public function registerCacheTagsToFlush(array $tags): self
    {
        $this->cacheTagsToFlush = $tags;

        return $this;
    }

    public function setIndexable(bool $searchable, array $indexableKeys = []): self
    {
        $this->isIndexable = $searchable;
        $this->indexableKeys = $indexableKeys;

        return $this;
    }
}
