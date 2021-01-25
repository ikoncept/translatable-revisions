<?php

namespace Infab\TranslatableRevisions\Traits;

use Exception;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Infab\TranslatableRevisions\Events\DefinitionsPublished;
use Infab\TranslatableRevisions\Events\DefinitionsUpdated;
use Infab\TranslatableRevisions\Models\I18nDefinition;
use Infab\TranslatableRevisions\Models\I18nLocale;
use Infab\TranslatableRevisions\Models\I18nTerm;
use Infab\TranslatableRevisions\Models\RevisionMeta;
use Infab\TranslatableRevisions\Models\RevisionTemplate;
use Infab\TranslatableRevisions\Models\RevisionTemplateField;
use Illuminate\Support\Str;

trait HasTranslatedRevisions
{
    abstract public function getRevisionOptions(): RevisionOptions;

    /**
     * Revision Options
     *
     * @var RevisionOptions
     */
    protected $revisionOptions;


    /**
     * locale
     *
     * @var string
     */
    public $locale = '';

    /**
     * Is the model being published
     *
     * @var boolean
     */
    public $isPublishing = false;

    /**
     * revisionNumber
     *
     * @var int
     */
    public $revisionNumber;

    /**
     * Set the locale, with fallback
     *
     * @param string $locale
     * @return string
     */
    public function setLocale($locale) : string
    {
        if ($locale) {
            $this->locale = $locale;
        } else {
            $this->locale = App::getLocale();
        }

        return $this->locale;
    }

    /**
     * Sets the revision, with default fallback
     *
     * @param integer|null $revision
     * @return int
     */
    public function setRevision($revision) : int
    {
        if ($revision) {
            $this->revisionNumber = $revision;
        } else {
            $this->revisionNumber = $this->revision;
        }

        return (int) $this->revisionNumber;
    }

    protected static function bootHasTranslatedRevisions() : void
    {
        static::deleting(function ($model) {
            $termKey = $model->getTable() . '_' . $model->id . '_';
            // Clear meta
            $model->meta()->delete();
            // Clear terms/defs
            DB::table('i18n_terms')->where('key', 'LIKE', $termKey . '%')->delete();
        });
    }

    /**
     * Relation for revisiontemplates
     *
     * @return BelongsTo
     */
    public function template() : BelongsTo
    {
        return $this->belongsTo(RevisionTemplate::class, 'template_id', 'id');
    }

    /**
     * Relation for meta
     *
     * @return MorphMany
     */
    public function meta(): MorphMany
    {
        return $this->morphMany(RevisionMeta::class, 'model');
    }

    /**
     * Update content for fields
     *
     * @param array $fieldData
     * @param string|null $locale
     * @param int|null $revision
     * @return Collection
     */
    public function updateContent(array $fieldData, $locale = null, $revision = null) : Collection
    {
        // Revision is always the current, if not overridden
        $this->setLocale($locale);
        $this->setRevision($revision);

        $definitions = collect($fieldData)->map(function ($data, $fieldKey) {
            $identifier =  $this->getTable() . '_' . $this->id .'_'. $this->revisionNumber . '_' . $fieldKey;

            // Remove stale terms
            DB::table('i18n_terms')->where('key', 'LIKE', $identifier . '%')->delete();

            $templateField = RevisionTemplateField::where('key', $fieldKey)->first();

            // TODO
            if (! $templateField->translated && ! $templateField->repeater) {
                $updated = RevisionMeta::updateOrCreate(
                    ['meta_key' => $fieldKey,
                    'model_id' => $this->id,
                    'model_type' => self::class,
                    'model_version' => $this->revisionNumber],
                    [
                        'meta_value' => $data
                    ]
                );
                return $updated;
            }


            if (is_array($data) && ! Arr::isAssoc($data)) {
                // if ($templateField->type == 'image') {
                //     $multiData = $data;
                // } else {
                $multiData = $this->handleSequentialArray($data, $fieldKey, $templateField);
                // }


                $updated = RevisionMeta::updateOrCreate(
                    ['meta_key' => $fieldKey,
                    'model_id' => $this->id,
                    'model_type' => self::class,
                    'model_version' => $this->revisionNumber],
                    [
                        'meta_value' => $multiData
                    ]
                );

                return ['definition' => $multiData, 'term' => $identifier, 'meta' => $updated];
            } else {
                // Find term
                $term = I18nTerm::updateOrCreate(
                    ['key' => $identifier],
                    ['description' => $templateField->name . ' for ' . $this->title]
                );
                $definition = I18nDefinition::updateOrCreate(
                    [
                        'term_id' => $term->id,
                        'locale' => $this->locale
                    ],
                    ['content' => $data]
                );

                return ['definition' => $definition, 'term' => $term];
            }
        });

        app()->events->dispatch(new DefinitionsUpdated($definitions, $this));

        return $definitions;
    }

    protected function getTemplateJoinStatement() : Expression
    {
        return (get_class($this->getConnection()) === 'Illuminate\Database\SQLiteConnection')
            ? DB::raw("'%' || revision_template_fields.key || '%'")
            : DB::raw("concat( '%',revision_template_fields.key,'%' )");
    }

    /**
     * Get the content for the field
     *
     * @param integer|null $revision
     * @param string|null $locale
     * @return Collection
     */
    public function getFieldContent($revision = null, $locale = null) : Collection
    {
        $this->setLocale($locale);
        $this->setRevision($revision);

        $termWithoutKey =  $this->getTable() . '_' . $this->id .'_'. $revision . '_';


        $translatedFields = DB::table('i18n_terms')
            ->leftJoin('i18n_definitions', 'i18n_terms.id', '=', 'i18n_definitions.term_id')
            ->leftJoin('revision_template_fields', 'i18n_terms.key', 'LIKE',  $this->getTemplateJoinStatement())
            ->select(
                'i18n_terms.id', 'i18n_terms.key',
                'i18n_terms.id as term_id',
                'i18n_definitions.content',
                'revision_template_fields.key as template_key')
            ->where('i18n_terms.key', 'LIKE', $termWithoutKey . '%')
            ->where('i18n_definitions.locale', $this->locale)
            ->get();

        $metaFields = $this->meta()
            ->leftJoin('revision_template_fields', 'revision_meta.meta_key', '=', 'revision_template_fields.key')
            ->select(
                'revision_meta.*',
                'revision_template_fields.type'
            )
            ->where('model_version', $revision)
            ->get();

        $metaData = $metaFields->mapWithKeys(function($metaItem) {
            return [$metaItem->meta_key => $this->getMeta($metaItem)];
        });


        $grouped = collect($translatedFields)->mapWithKeys(function($item) {
            return [$item->template_key  => json_decode($item->content)];
        });

        return $grouped->merge($metaData);
    }

    /**
     * Removes old revisions
     *
     * @param int $revision
     * @return void
     */
    public function purgeOldRevisions($revision)
    {
        $identifier =  $this->getTable() . '_' . $this->id .'_'. $revision . '_';

        I18nTerm::where('key', 'LIKE', $identifier . '%')->get()
            ->each(function ($item) {
                $item->definitions()->delete();
                $item->delete();
            });
        DB::table(config('translatable-revisions.revision_meta_table_name'))
            ->where('model_version', '<=', $revision)
            ->where('model_type', self::class)
            ->where('model_id', $this->id)
            ->delete();
    }


    /**
     * Translate by term key
     *
     * @param string $termKey
     * @param RevisionTemplateField|null $field
     * @return mixed
     */
    public function translateByKey(string $termKey, ?RevisionTemplateField $field = null)
    {
        if (!$termKey) {
            return '';
        }

        $value = DB::table('i18n_terms')
            ->leftJoin('i18n_definitions', 'term_id', '=', 'i18n_terms.id')
            ->where([
                ['key', '=', $termKey],
                ['locale', '=', $this->locale]
            ])->value('content');
        $value = json_decode($value, true);



        if (! $field) {
            return $value;
        }
        return $value;
    }


    /**
     * Get meta value
     *
     * @param RevisionMeta $meta
     * @return array|null
     */
    public function getMeta(RevisionMeta $meta)
    {
        $metaValue = $meta->meta_value;
        $value = null;

        $getters = $this->getRevisionOptions()->getters;

        if (array_key_exists($meta->type, $getters)) {
            $callable = [$this,  $this->getRevisionOptions()->getters[$meta->type]];
            $value = $this->handleCallable($callable, $meta);
        } else {
            $value = $metaValue;
        }
        return $value ? $value : null;
    }


    /**
     * Handle callable
     *
     * @param mixed $callable
     * @param RevisionMeta|null $meta
     * @return mixed
     */
    public function handleCallable($callable, $meta)
    {
        try {
            return call_user_func_array($callable, [
                $meta ?? []
            ]);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Get repeater
     *
     * @param mixed $data
     * @return array
     */
    public function getRepeater($data) : array
    {
        $repeater = $data->meta_value;

        return collect($repeater)->map(function($repeaterItem) {
            return collect($repeaterItem)->map(function($termKey) {
                // Translate via the termkey
                return $this->translateByKey($termKey);
            })->map(function($translatedItem, $key) {
                // If the key contains children, handle children
                if(Str::contains($key, 'children')) {
                    return $this->handleChildRepeater($translatedItem);
                }
                // If there is an image to take care of
                if(Str::contains($key, 'image')) {
                    return $this->handleCallable(
                        [$this,  $this->getRevisionOptions()->getters['image']],
                        RevisionMeta::make([
                            'meta_value' => $translatedItem
                        ])
                    );
                }
                // Otherwise return the translated item as is
                return $translatedItem;
            });
        })->toArray();
    }

    /**
     * Handle child repeater
     *
     * @param array|null $translatedItem
     * @return Collection
     */
    public function handleChildRepeater($translatedItem) : Collection
    {
        if( ! $translatedItem) {
            return collect([]);
        }
        return collect($translatedItem)->transform(function($child) {
            return collect($child)->map(function($item, $key) {
                if(Str::contains($key, 'image')) {
                    return $this->handleCallable(
                        [$this,  $this->getRevisionOptions()->getters['image']],
                        RevisionMeta::make([
                            'meta_value' => $item
                        ])
                    );
                }
                return $item;
            });
        });
    }

    /**
     * Publish a specified revision
     *
     * @param int $revision
     * @return mixed
     */
    public function publish(int $revision)
    {
        // Should publish all languages?
        // NEW
        $f = I18nLocale::where('enabled', 1)->count();
        $this->isPublishing = true;
        $updatedContent = I18nLocale::where('enabled', 1)
            ->get()->mapWithKeys(function ($locale) use ($revision) {
                $content = $this->getFieldContent($revision, $locale->iso_code);
                $oldRevision = $revision - 1;
                $this->published_version = $revision;
                $this->revision = $revision + 1;
                $this->published_at = now();
                $this->updateContent($content->toArray(), $locale->iso_code, $this->revision);
                $this->save();

                $this->purgeOldRevisions($oldRevision);
                return [$locale->iso_code => $this->getFieldContent($revision, $locale->iso_code)];
            });

        app()->events->dispatch(new DefinitionsPublished($updatedContent, $this));

        return $this;
    }

    /**
     * Handles sequentials arrays, used for repeaters
     *
     * @param array $data
     * @param string $fieldKey
     * @param RevisionTemplateField $templateField
     * @return Collection
     */
    protected function handleSequentialArray(array $data, $fieldKey, $templateField) : Collection
    {
        return collect($data)->map(function ($item, $index) use ($fieldKey, $templateField) {
            $item = collect($item)->map(function ($subfield, $key) use ($fieldKey, $index, $templateField) {
                $identifier = $this->getTable() . '_' . $this->id . '_' . $this->revisionNumber . '_' . $fieldKey . '__' . $index . '_' . $key;

                // Create/Update the term
                $term = I18nTerm::updateOrCreate(
                    ['key' => $identifier],
                    ['description' => $templateField->name . ' for ' . $this->title]
                );

                I18nDefinition::updateOrCreate(
                    [
                        'term_id' => $term->id,
                        'locale' => $this->locale
                    ],
                    ['content' => $subfield]
                );

                return $identifier;
            });
            return $item;
        });
    }
}
