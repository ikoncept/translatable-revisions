<?php

namespace Infab\TranslatableRevisions\Traits;

use Exception;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Infab\TranslatableRevisions\Events\DefinitionsPublished;
use Infab\TranslatableRevisions\Events\DefinitionsUpdated;
use Infab\TranslatableRevisions\Events\TranslatedRevisionDeleted;
use Infab\TranslatableRevisions\Events\TranslatedRevisionUpdated;
use Infab\TranslatableRevisions\Exceptions\FieldKeyNotFound;
use Infab\TranslatableRevisions\Models\I18nDefinition;
use Infab\TranslatableRevisions\Models\I18nLocale;
use Infab\TranslatableRevisions\Models\I18nTerm;
use Infab\TranslatableRevisions\Models\RevisionMeta;
use Infab\TranslatableRevisions\Models\RevisionTemplate;
use Infab\TranslatableRevisions\Models\RevisionTemplateField;

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
     * @var bool
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
     * @param  string  $locale
     */
    public function setLocale($locale): string
    {
        if ($locale) {
            $this->locale = $locale;
        } else {
            $this->locale = App::getLocale();
        }

        return $this->locale;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Sets the revision, with default fallback
     *
     * @param  int|null  $revision
     */
    public function setRevision($revision): int
    {
        if ($revision) {
            $this->revisionNumber = $revision;
        } else {
            $this->revisionNumber = $this->revision;
        }

        return (int) $this->revisionNumber;
    }

    protected static function bootHasTranslatedRevisions(): void
    {
        static::deleting(function ($model) {
            $termKey = $model->getTable().$model->getDelimiter().$model->id.$model->getDelimiter();

            // Clear meta
            RevisionMeta::modelMeta($model)
                ->each(function ($item) {
                    $item->delete();
                });

            // Clear terms/defs
            (new I18nTerm)->clearTermsWithKey($termKey);
            app()->events->dispatch(new TranslatedRevisionDeleted($model));
        });

        static::updated(function ($model) {
            app()->events->dispatch(new TranslatedRevisionUpdated($model));
        });
    }

    /**
     * Relation for revisiontemplates
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(RevisionTemplate::class, 'template_id', 'id');
    }

    /**
     * Relation for meta
     */
    public function meta(): MorphMany
    {
        return $this->morphMany(RevisionMeta::class, 'model');
    }

    /**
     * Relation for terms
     */
    public function terms(): MorphMany
    {
        return $this->morphMany(I18nTerm::class, 'model');
    }

    /**
     * Gets the tempplate field via fieldKey
     *
     * @return \Infab\TranslatableRevisions\Models\RevisionTemplateField
     *
     * @throws FieldKeyNotFound
     */
    public function getTemplateField(string $fieldKey)
    {
        $defTemplateSlug = $this->getRevisionOptions()->defaultTemplate;
        try {
            $templateField = RevisionTemplateField::where('key', $fieldKey)
                ->whereHas('template', function ($query) use ($defTemplateSlug) {
                    if ($defTemplateSlug) {
                        $query->where('slug', $defTemplateSlug);
                    }
                })
                ->firstOrFail();

            return $templateField;
        } catch (\Exception $e) {
            throw FieldKeyNotFound::fieldKeyNotFound($fieldKey);
        }
    }

    /**
     * Update content for fields
     *
     * @param  string|null  $locale
     * @param  int|null  $revision
     */
    public function updateContent(array $fieldData, $locale = null, $revision = null): Collection
    {
        // Revision is always the current, if not overridden
        if (! $locale) {
            $this->setLocale($locale);
            $locale = $this->locale;
        }

        $this->setRevision($revision);

        $definitions = collect($fieldData)->map(function ($data, $fieldKey) use ($locale) {
            $delimter = $this->getDelimiter(true);
            $identifier = $this->getTable().$delimter.$this->id.$delimter.$this->revisionNumber.$delimter.$fieldKey;
            $templateField = $this->getTemplateField($fieldKey);

            // If the template field isn't translated and isn't a repeater, it's probably
            // a meta field
            if (! $templateField->translated && ! $templateField->repeater) {
                $term = $this->getTermWithoutKey($this->revisionNumber).$this->getDelimiter().$fieldKey;
                DB::table('i18n_terms')->whereRaw('i18n_terms.key LIKE ? ESCAPE ?', [$term.'%', '\\'])->delete();

                return $this->updateMetaItem($fieldKey, $data);
            }

            if (is_array($data) && ! Arr::isAssoc($data) && ! $templateField->translated) {
                // Repeater
                $multiData = $this->handleSequentialArray($data, $fieldKey, $templateField);

                $updated = $this->updateMetaItem($fieldKey, $multiData);

                return ['definition' => $multiData, 'term' => $identifier, 'meta' => $updated];
            } else {
                // Translated field
                [$term, $definition] = $this->updateOrCreateTermAndDefinition($identifier, $templateField, $locale, $data);

                return ['definition' => $definition, 'term' => $term];
            }
        });

        app()->events->dispatch(new DefinitionsUpdated($definitions, $this));

        return $definitions;
    }

    /**
     * Update or creates terms and definitions
     *
     * @param  mixed  $data
     */
    public function updateOrCreateTermAndDefinition(string $identifier, RevisionTemplateField $templateField, string $locale, $data, bool $transformData = true): array
    {
        $term = I18nTerm::updateOrCreate(
            ['key' => $identifier],
            ['description' => $templateField->name.' for '.$this->title]
        );
        $definition = I18nDefinition::updateOrCreate(
            [
                'term_id' => $term->id,
                'locale' => $locale,
            ],
            ['content' => $transformData ? $this->transformData($data, $templateField) : $data]
        );

        $this->terms()->save($term);

        return [$term, $definition];
    }

    /**
     * Transform array to an array with id only
     *
     * @param  mixed  $data
     * @return mixed
     */
    protected function fromArrayToIdArray($data)
    {
        if (empty($data)) {
            return null;
        }

        return $data;

        if (is_array($data) && Arr::isAssoc($data)) {
            if (isset($data['id'])) {
                return [$data['id']];
            } else {
                return $data;
            }
        }

        if (is_array($data) && ! Arr::isAssoc($data)) {
            if (is_numeric($data[0])) {
                return $data;
            }
            if (is_array($data[0])) {
                if (! array_key_exists('id', $data[0])) {
                    return $data;
                }
            }

            if (! array_key_exists('id', $data)) {
                return collect($data)->toArray();
            }

            return collect($data)->pluck('id')->toArray();
        }

        return $data;
    }

    /**
     * Transform images and children
     *
     * @param  mixed  $data
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

    protected function handleSpecialTypes(array $repeater): array
    {
        return collect($repeater)->filter(function ($item, $key) use ($repeater) {
            if (empty($repeater[$key])) {
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

    public function getDelimiter(bool $isSaving = false): string
    {
        $delimiterConfig = config('translatable-revisions.delimiter');

        if ($delimiterConfig === '_') {
            if ($isSaving) {
                return $delimiterConfig;
            }

            return '\_';
        }

        return $delimiterConfig;
    }

    /**
     * Get the compound term key
     *
     * @param  int|null  $revision
     */
    protected function getTermWithoutKey($revision = null): string
    {
        $delimter = $this->getDelimiter();
        if ($revision) {
            $rev = $revision;
        } else {
            $rev = $this->revisionNumber;
        }

        return $this->getTable().$delimter.$this->id.$delimter.$rev.$delimter;
    }

    /**
     * Get the content for the field without using getters
     *
     * @param  int|null  $revision
     * @param  string|null  $locale
     */
    public function getSimpleFieldContent($revision = null, $locale = null): Collection
    {
        $this->setLocale($locale);
        $this->setRevision($revision);

        // Escape wild card chars
        $termWithoutKey = $this->getTermWithoutKey();

        $translatedFields = I18nTerm::translatedFields(
            $termWithoutKey,
            $this->locale
        )->get();

        $metaFields = RevisionMeta::modelMeta($this)
            ->metaFields($revision)
            ->get();

        $metaData = $metaFields->mapWithKeys(function ($metaItem) {
            return [$metaItem->meta_key => $this->getMeta($metaItem)];
        });

        $grouped = collect($translatedFields)->mapWithKeys(function ($item, $key) {
            if ($item->repeater) {
                $content = json_decode($item->content, true);

                return [$item->template_key => $content];
            }

            return [$item->template_key => json_decode($item->content)];
        });

        return $grouped->merge($metaData);
    }

    /**
     * Get the content for the field
     *
     * @param  int|null  $revision
     * @param  string|null  $locale
     */
    public function getFieldContent($revision = null, $locale = null): Collection
    {
        $this->setLocale($locale);
        $this->setRevision($revision);

        // Escape wild card chars
        $termWithoutKey = $this->getTermWithoutKey();

        $translatedFields = I18nTerm::translatedFields(
            $termWithoutKey,
            $this->locale
        )->get();

        $metaFields = RevisionMeta::modelMeta($this)
            ->metaFields($revision)
            ->get();

        $metaData = $metaFields->mapWithKeys(function ($metaItem) {
            return [$metaItem->meta_key => $this->getMeta($metaItem)];
        });

        $grouped = collect($translatedFields)->mapWithKeys(function ($item, $key) {
            if ($item->repeater) {
                $content = json_decode($item->content, true);

                return [$item->template_key => $this->getRepeater($content)];
            }

            if (in_array($item->type, $this->getRevisionOptions()->specialTypes)) {
                if (array_key_exists($item->type, $this->getRevisionOptions()->getters)) {

                    return [
                        $item->template_key => $this->handleCallable(
                            [$this,  $this->getRevisionOptions()->getters[$item->type]],
                            RevisionMeta::make([
                                'meta_value' => json_decode($item->content),
                            ])
                        ),
                    ];
                }
            }

            return [$item->template_key => json_decode($item->content)];
        });

        return $grouped->merge($metaData);
    }

    /**
     * Removes old revisions
     *
     * @param  int  $revision
     * @return void
     */
    public function purgeOldRevisions($revision)
    {
        $identifier = $this->getTermWithoutKey($revision);

        I18nTerm::whereRaw('i18n_terms.key LIKE ? ESCAPE ?', [$identifier.'%', '\\'])->get()
            ->each(function ($item) {
                $item->definitions()->delete();
                $item->delete();
            });
        DB::table(config('translatable-revisions.revision_meta_table_name'))
            ->where('model_version', '<=', $revision)
            ->where('model_type', $this->morphClass ?? $this->getMorphClass())
            ->where('model_id', $this->id)
            ->delete();
    }

    /**
     * Translate by term key
     *
     * @return mixed
     */
    public function translateByKey(string $termKey, string $locale)
    {
        if (! $termKey) {
            return '';
        }

        $value = DB::table('i18n_terms')
            ->leftJoin('i18n_definitions', 'term_id', '=', 'i18n_terms.id')
            ->where([
                ['key', '=', $termKey],
                ['i18n_definitions.locale', '=', $locale],
            ])->value('content');
        $value = json_decode($value, true);

        return $value;
    }

    /**
     * Get meta value
     *
     * @param  RevisionMeta|\Illuminate\Database\Eloquent\Model  $meta
     * @return array|null
     */
    public function getMeta($meta)
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
     * @param  string|int  $fieldKey
     * @param  mixed  $data
     */
    public function updateMetaItem($fieldKey, $data): RevisionMeta
    {
        $updated = RevisionMeta::updateOrCreate(
            ['meta_key' => $fieldKey,
                'model_id' => $this->id,
                'model_type' => $this->morphClass ?? $this->getMorphClass(),
                'model_version' => $this->revisionNumber, ],
            [
                'meta_value' => $this->fromArrayToIdArray($data),
            ]
        );

        return $updated;
    }

    /**
     * Update a meta items with an array of data
     */
    public function updateMetaContent(array $data): array
    {
        $updatedItems = [];
        foreach ($data as $key => $content) {
            $updatedItems[] = $this->updateMetaItem($key, $content);
        }

        return $updatedItems;
    }

    /**
     * Handle callable
     *
     * @param  mixed  $callable
     * @param  \Illuminate\Database\Eloquent\Model|RevisionMeta|null  $meta
     * @return mixed
     */
    public function handleCallable($callable, $meta)
    {
        try {
            return call_user_func_array($callable, [
                $meta ?? [],
            ]);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Get repeater
     *
     * @param  mixed  $repeater
     */
    public function getRepeater($repeater): array
    {
        return collect($repeater)->map(function ($child) {
            return collect($child)->map(function ($value, $key) {
                // Check if key exists in the revision options
                if (array_key_exists($key, $this->getRevisionOptions()->getters)) {
                    return $this->handleCallable(
                        [$this,  $this->getRevisionOptions()->getters[$key]],
                        RevisionMeta::make([
                            'meta_value' => $value,
                        ])
                    );
                }
                if (Str::contains($key, 'children')) {
                    return $this->handleChildRepeater($value);
                }

                return $value;
            });
        })->toArray();
    }

    /**
     * Handle child repeater
     *
     * @param  array|null  $translatedItem
     */
    public function handleChildRepeater($translatedItem): Collection
    {
        if (! $translatedItem) {
            return collect([]);
        }

        return collect($translatedItem)->transform(function ($child) {
            return collect($child)->map(function ($item, $key) {
                if (array_key_exists($key, $this->getRevisionOptions()->getters)) {
                    return $this->handleCallable(
                        [$this,  $this->getRevisionOptions()->getters[$key]],
                        RevisionMeta::make([
                            'meta_value' => $item,
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
     * @return mixed
     */
    public function publish(int $suppliedRevision)
    {
        $updatedContent = I18nLocale::where('enabled', 1)
            ->get()->mapWithKeys(function ($locale) use ($suppliedRevision) {
                $unpublishedContent = $this->getFieldContent($suppliedRevision, $locale->iso_code);

                // Move revisions
                $this->published_version = $suppliedRevision;
                $this->revision = $suppliedRevision + 1;
                $this->published_at = now();

                // Set content for new revision
                $this->updateContent($unpublishedContent->toArray(), $locale->iso_code, $this->revision);
                $this->save();

                $this->purgeOldRevisions($suppliedRevision - 1);

                return [$locale->iso_code => $this->getFieldContent($suppliedRevision, $locale->iso_code)];
            });

        app()->events->dispatch(new DefinitionsPublished($updatedContent, $this));

        return $this;
    }

    /**
     * Handles sequentials arrays, used for repeaters
     *
     * @param  string  $fieldKey
     * @param  RevisionTemplateField  $templateField
     */
    protected function handleSequentialArray(array $data, $fieldKey, $templateField): Collection
    {
        return collect($data)->map(function ($item, $index) use ($fieldKey, $templateField) {
            $item = collect($item)->map(function ($subfield, $key) use ($fieldKey, $index, $templateField) {
                $delimiter = $this->getDelimiter(true);
                $identifier = $this->getTable().$delimiter.$this->id.$delimiter.$this->revisionNumber.$delimiter.$fieldKey.$delimiter.$delimiter.$index.$delimiter.$key;

                // Create/Update the term
                $this->updateOrCreateTermAndDefinition($identifier, $templateField, $this->locale, $subfield, false);

                return $identifier;
            });

            return $item;
        });
    }
}
