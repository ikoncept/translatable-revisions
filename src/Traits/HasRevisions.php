<?php

namespace Infab\PageModule\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Infab\PageModule\Models\I18nDefinition;
use Infab\PageModule\Models\I18nLocale;
use Infab\PageModule\Models\I18nTerm;
use Infab\PageModule\Models\PageMeta;
use Infab\PageModule\Models\PageTemplate;
use Infab\PageModule\Models\PageTemplateField;

trait HasRevisions
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
        return $this->belongsTo(PageTemplate::class, 'template_id', 'id');
    }

    public function meta() : HasMany
    {
        return $this->hasMany(PageMeta::class);
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
            $templateField = PageTemplateField::where('key', $field)->first();
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

                $updated = PageMeta::updateOrCreate(
                    ['meta_key' => $field,
                    'page_id' => $this->id,
                    'page_version' => $revision],
                    [
                        'meta_value' => $multiData->toJson()
                    ]
                );
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

                return $definition;
            }
        });

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
                if($field->type != 'grid') {
                    return collect([$field->key => '']);
                }
                // Check pagemeta
                $meta = $this->meta()->where('page_version', $revision)->first();
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
        $locales = I18nLocale::where('enabled', 1)
            ->get()->each(function ($locale) use ($revision) {
                $content = $this->getFieldContent($locale, $revision);

                $oldRevision = $revision - 1;
                $this->published_version = $revision;
                $this->revision = $revision + 1;
                $this->published_at = now();
                $this->updateContent($content->toArray(), $this->revision, $locale->iana_code);
                $this->save();

                $this->purgeOldRevisions($oldRevision);
            });

        return $this;
    }
}
