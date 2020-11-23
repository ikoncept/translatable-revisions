<?php

namespace Infab\TranslatableRevisions\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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
    public $locale;

    public function setLocale($locale)
    {
        if ($locale) {
            $this->locale = $locale;
        } else {
            $this->locale = App::getLocale();
        }

        return $this->locale;
    }

    public function template() : BelongsTo
    {
        return $this->belongsTo(RevisionTemplate::class, 'template_id', 'id');
    }

    public function meta(): MorphMany
    {
        return $this->morphMany(RevisionMeta::class, 'model');
    }

    /**
     * Update content for multiple fields
     *
     * @param array $fieldData
     * @param string $locale
     * @return Collection
     */
    public function updateContent(array $fieldData, $revision = 1, $locale = null) : Collection
    {
        $this->setLocale($locale);

        $definitions = collect($fieldData)->map(function ($data, $field) use ($revision) {
            $templateField = RevisionTemplateField::where('key', $field)->first();
            $identifier = 'page_' . $this->id .'_'. $revision . '_' . $field;


            // Create or update definition

            // Hmm, an array
            if (is_array($data)) {
                $multiData = collect($data)->map(function ($item, $index) use ($field, $revision, $templateField) {
                    $item = collect($item)->map(function ($subfield, $key) use ($revision, $field, $index, $templateField) {
                        $identifier = 'page_' . $this->id . '_' . $revision . '_' . $field . '__' . $index . '_' . $key;

                        // Create/Update the term
                        $term = I18nTerm::updateOrCreate(
                            ['key' => $identifier],
                            ['description' => $templateField->name . ' for ' . $this->title]
                        );

                        $definition = I18nDefinition::updateOrCreate(
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

                $updated = RevisionMeta::updateOrCreate(
                    ['meta_key' => $field,
                    'model_id' => $this->id,
                    'model_type' => self::class,
                    'model_version' => $revision],
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
     * @return void
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
                $this->updateContent($content->toArray(), $this->revision, $locale->iso_code);
                $this->save();

                $this->purgeOldRevisions($oldRevision);
                return [$locale->iso_code => $this->getFieldContent($revision, $locale->iso_code)];
            });

        app()->events->dispatch(new DefinitionsPublished($updatedContent, $this));

        return $this;
    }
}
