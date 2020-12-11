<?php

namespace Infab\TranslatableRevisions\Traits;

use Exception;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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

trait HasTranslatedRevisions
{
    abstract public function getRevisionOptions(): RevisionOptions;

    /**
     * Revision Options
     *
     * @var RevisionOptions
     */
    protected  $revisionOptions;


    /**
     * locale
     *
     * @var string
     */
    public $locale = '';

    /**
     * revisionNumber
     *
     * @var int
     */
    public $revisionNumber = 1;

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
     * @param integer|null $revisionNumber
     * @return int
     */
    public function setRevision($revisionNumber) : int
    {
        $this->revisionNumber = $this->revision;

        if ($revisionNumber) {
            $this->revisionNumber = $revisionNumber;
        }

        return (int) $this->revisionNumber;
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
            $templateField = RevisionTemplateField::where('key', $fieldKey)->first();
            if(! $templateField->translated) {
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
            $identifier =  $this->getTable() . '_' . $this->id .'_'. $this->revisionNumber . '_' . $fieldKey;


            if (is_array($data) && ! Arr::isAssoc($data)) {
                if ($templateField->type == 'image') {
                    $multiData = $data;
                } else {
                    $multiData = $this->handleSequentialArray($data, $fieldKey, $templateField);
                }


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

    /**
     * Get the content for the field
     *
     * @param integer|null $revision
     * @param string|null $locale
     * @return Collection
     */
    public function getFieldContent($revision = 1, $locale = null) : Collection
    {
        $this->setLocale($locale);
        $this->setRevision($this->revision);
        $content = [];
        foreach ($this->template->fields as $field) {
            $term =  $this->getTable() . '_' . $this->id .'_'. $revision . '_' . $field->key;
            if ($field->translated) {
                $content[$field->key] = $this->translateField($term, $field);
            } else {
                $content[$field->key] = $this->getMeta($term, $field);
            }
        }

        return collect($content);
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

        I18nTerm::where('key', 'LIKE', $identifier . '%')
            ->get()->each(function ($item) {
                $item->definitions()->delete();
                $item->delete();
            });
    }


    /**
     * Translate a field
     *
     * @param string $termKey
     * @param RevisionTemplateField $field
     * @param bool $full
     * @return mixed
     */
    public function translateField(string $termKey, RevisionTemplateField $field, $full = false)
    {
        if (!$full) {
            $translated = $this->translateByKey($termKey, $field);

            return $translated == $termKey ? '' : $translated;
        }

        return '';
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



        if(! $field) {
            return $value;
        }
        // if ($field->type == 'image') {
        //     if (isset($value['meta_value'])) {
        //         $media = $this->getImages($value['meta_value']);
        //         if ($media) {
        //             $value = $media;
        //         } else {
        //             $value = [];
        //         }
        //     }
        // }
        return $value;
    }


    /**
     * Get meta value
     *
     * @param string $termKey
     * @param RevisionTemplateField $field
     * @return array
     */
    public function getMeta($termKey, RevisionTemplateField $field)
    {
        $meta = $this->meta()->where('meta_key', $field->key)
            ->where('model_version', $this->revisionNumber)
            ->first();

        if (! $meta) {
            $meta = new RevisionMeta();
        }

        $metaValue = $meta->meta_value;
        $value = null;

        $getters = $this->getRevisionOptions()->getters;

        if (array_key_exists($field->type, $getters)) {
            $callable = [$this,  $this->getRevisionOptions()->getters[$field->type]];
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
     * @param array $data
     * @return array
     */
    public function getRepeater($data) : array
    {
        if (! $data) {
            return [];
        }
        $repeater = $data;

        foreach ($repeater as &$field) {
            foreach ($field as $key => $termKey) {
                $field[$key] = $this->translateByKey($termKey);
            }
        }

        return $repeater;
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
