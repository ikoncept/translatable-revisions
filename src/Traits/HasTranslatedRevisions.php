<?php

namespace Infab\TranslatableRevisions\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
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
     *1
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
            $identifier = 'page_' . $this->id .'_'. $this->revisionNumber . '_' . $fieldKey;


            if (is_array($data) && ! Arr::isAssoc($data)) {
                $multiData = $this->handleSequentialArray($data, $fieldKey, $templateField);

                $updated = RevisionMeta::updateOrCreate(
                    ['meta_key' => $fieldKey,
                    'model_id' => $this->id,
                    'model_type' => self::class,
                    'model_version' => $this->revisionNumber],
                    [
                        'meta_value' => $multiData->toJson()
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
        $contentMap = $this->template->fields->mapWithKeys(function ($field) use ($revision) {
            $term = 'page_' . $this->id .'_'. $revision . '_' . $field->key;

            $content = I18nTerm::where('key', $term)
                ->whereHas('definitions', $definitions = function ($query) {
                    $query->where('locale', $this->locale);
                })
                ->with('definitions', $definitions)
                ->first();


            if (! $content) {
                if(! $field->repeater) {
                    return collect([$field->key => '']);
                }
                // Check RevisionMeta
                $meta = $this->meta()->where('model_version', $revision)->first();
                if (! $meta) {
                    return collect([]);
                }
                $multiContent = collect(json_decode($meta->meta_value, true))->map(function ($item) {
                    $content = collect($item)->map(function ($metaKey) {
                        $term = I18nTerm::where('key', $metaKey)
                            ->first();
                        $definition = I18nDefinition::where('term_id', $term->id)
                            ->where('locale', $this->locale)
                            ->first();
                        if(! $definition) {
                            return null;
                        }
                        return $definition->content;
                    });
                    return $content;
                });

                return [$field['key'] => $multiContent->toArray()];
            }
            return [
                $field['key'] => $content->definitions->first()->content
            ];
        });

        return $contentMap;
    }

    /**
     * Removes old revisions
     *
     * @param int $revision
     * @return void
     */
    public function purgeOldRevisions($revision)
    {
        $identifier = 'page_' . $this->id .'_'. $revision . '_' ;

        I18nTerm::where('key', 'LIKE', $identifier . '%')
            ->get()->each(function ($item) {
                $item->definitions()->delete();
                $item->delete();
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
                $identifier = 'page_' . $this->id . '_' . $this->revisionNumber . '_' . $fieldKey . '__' . $index . '_' . $key;

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
