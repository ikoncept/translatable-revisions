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
use Illuminate\Support\Facades\Log;
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
    protected $locale = '';

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

    public function getLocale() : string
    {
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
        if(! $locale) {
            $this->setLocale($locale);
            $locale = $this->locale;
        }
        $this->setRevision($revision);

        $definitions = collect($fieldData)->map(function ($data, $fieldKey) use ($locale) {
            $identifier =  $this->getTable() . '_' . $this->id .'_'. $this->revisionNumber . '_' . $fieldKey;


            // Should check if the template field is connected to the chosen template
            $defTemplateSlug = $this->getRevisionOptions()->defaultTemplate;
            try {
                $templateField = RevisionTemplateField::where('key', $fieldKey)
                    ->whereHas('template', function($query) use ($defTemplateSlug) {
                        if($defTemplateSlug) {
                            $query->where('slug', $defTemplateSlug);
                        }
                    })
                    ->firstOrFail();
            } catch (\Exception $e) {
                abort(500, 'Field key not found for: ' . $fieldKey);
            }


            // TODO
            if (! $templateField->translated && ! $templateField->repeater) {
                DB::table('i18n_terms')->where('key', 'LIKE', $identifier . '%')->delete();

                return $this->updateMetaItem($fieldKey, $data);
            }


            if (is_array($data) && ! Arr::isAssoc($data) && ! $templateField->translated) {

                $multiData = $this->handleSequentialArray($data, $fieldKey, $templateField);


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
                        'locale' => $locale,
                    ],
                    ['content' => $this->transformData($data, $templateField)]
                );

                return ['definition' => $definition, 'term' => $term];
            }
        });

        app()->events->dispatch(new DefinitionsUpdated($definitions, $this));

        return $definitions;
    }

    /**
     * Transform array to an array with id only
     *
     * @param mixed $data
     * @return mixed
     */
    protected function fromArrayToIdArray($data)
    {
        if(empty($data)) {
            return null;
        }
        if(is_array($data) && Arr::isAssoc($data)) {
            if(isset($data['id'])) {
                return [$data['id']];
            } else {
                return $data;
            }
        }
        if(is_array($data) && ! Arr::isAssoc($data)) {
            if(is_numeric($data[0])) {
                return $data;
            }
            if(! array_key_exists('id', $data[0])) {
                return $data;
            }
            return collect($data)->pluck('id')->toArray();
        }
        return $data;
    }

    /**
     * Transform images and children
     *
     * @param mixed $data
     * @param RevisionTemplateField $templateField
     * @return mixed
     */
    protected function transformData($data, RevisionTemplateField $templateField)
    {
        // Clean this up, atm hardcoded to images and children
        if ($templateField->repeater) {
            return collect($data)->map(function ($repeater) {
                if (array_key_exists('children', $repeater)) {
                    $repeater['children'] = collect($repeater['children'])->transform(function ($child) {
                        return $this->handleSpecialTypes($child);
                    });
                }
                $repeater = $this->handleSpecialTypes($repeater);

                return $repeater;
            });
        }

        // Transform whole objects to their ids
        if (in_array($templateField->type, $this->getRevisionOptions()->specialTypes)) {
            $data = $this->fromArrayToIdArray($data);
        }

        return $data;
    }

    protected function handleSpecialTypes(array $repeater) : array
    {
        return collect($repeater)->filter(function($item, $key) use ($repeater) {
            if(empty($repeater[$key])) {
                return false;
            }
            return true;
        })->map(function ($value, $key) {
            if (in_array($key, $this->getRevisionOptions()->specialTypes)) {
                return $this->fromArrayToIdArray($value);
            }
            return $value;
        })->toArray();
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
                'revision_template_fields.repeater',
                'revision_template_fields.type',
                'revision_template_fields.translated',
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

        // $repeaterTypes =

        $grouped = collect($translatedFields)->mapWithKeys(function($item, $key) {
            if($item->repeater) {
                $content = json_decode($item->content, true);

                return [$item->template_key  => $this->getRepeater($content)];
            }

            if(in_array($item->type, $this->getRevisionOptions()->specialTypes)) {
                if(array_key_exists($item->type, $this->getRevisionOptions()->getters))  {
                    return [
                        $item->template_key  => $this->handleCallable(
                            [$this,  $this->getRevisionOptions()->getters[$item->type]],
                            RevisionMeta::make([
                                'meta_value' => json_decode($item->content)
                            ])
                        )
                    ];
                }
            }

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
     * @return mixed
     */
    public function translateByKey(string $termKey, string $locale)
    {
        if (!$termKey) {
            return '';
        }

        $value = DB::table('i18n_terms')
            ->leftJoin('i18n_definitions', 'term_id', '=', 'i18n_terms.id')
            ->where([
                ['key', '=', $termKey],
                ['i18n_definitions.locale', '=', $locale]
            ])->value('content');
        $value = json_decode($value, true);

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

        if (array_key_exists($meta->meta_key, $this->getRevisionOptions()->getters)) {
            $callable = [$this,  $this->getRevisionOptions()->getters[$meta->meta_key]];
            $value = $this->handleCallable($callable, $meta);
        } else {
            $value = $metaValue;
        }
        return $value ? $value : null;
    }

    /**
     * Update a specific meta item
     *
     * @param string|int $fieldKey
     * @param mixed $data
     * @return RevisionMeta
     */
    public function updateMetaItem($fieldKey, $data) : RevisionMeta
    {
        $updated = RevisionMeta::updateOrCreate(
            ['meta_key' => $fieldKey,
            'model_id' => $this->id,
            'model_type' => self::class,
            'model_version' => $this->revisionNumber],
            [
                'meta_value' => $this->fromArrayToIdArray($data)
            ]
        );
        return $updated;
    }

    /**
     * Update a meta items with an array of data
     *
     * @param array $data
     * @return array
     */
    public function updateMetaContent(array $data) : array
    {
        $updatedItems = [];
        foreach($data as $key => $content) {
            $updatedItems[] = $this->updateMetaItem($key, $content);
        }

        return $updatedItems;
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
    public function getRepeaterLegacy($data) : array
    {
        $repeater = $data->meta_value;

        return collect($repeater)->map(function($repeaterItem) {
            return collect($repeaterItem)->map(function($termKey) {
                // Translate via the termkey
                return $this->translateByKey($termKey, $this->locale);
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
                // $this->transformIfImage($key, $translatedItem);
                // Otherwise return the translated item as is
                return $translatedItem;
            });
        })->toArray();
    }

    /**
     * Get repeater
     *
     * @param mixed $data
     * @return array
     */
    public function getRepeater($data) : array
    {
        $repeater = $data;

        return collect($repeater)->map(function($child) {
            return collect($child)->map(function($value, $key) {

                // Check if key existsss in the revision options
                if(array_key_exists($key, $this->getRevisionOptions()->getters))  {
                    return $this->handleCallable(
                        [$this,  $this->getRevisionOptions()->getters[$key]],
                        RevisionMeta::make([
                            'meta_value' => $value
                        ])
                    );
                }
                if(Str::contains($key, 'children')) {
                    return $this->handleChildRepeater($value);
                }
                return $value;
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
                if(array_key_exists($key, $this->getRevisionOptions()->getters))  {
                    return $this->handleCallable(
                        [$this,  $this->getRevisionOptions()->getters[$key]],
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
