<?php

namespace Infab\PageModule\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Infab\PageModule\Models\I18nDefinition;
use Infab\PageModule\Models\I18nTerm;
use Infab\PageModule\Models\PageMeta;
use Infab\PageModule\Models\PageTemplate;
use Infab\PageModule\Models\PageTemplateField;

trait HasRevisions
{
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
    public function updateContent(array $fieldData, string $locale = 'sv', $revision = 1) : Collection
    {
        $definitions = collect($fieldData)->map(function ($data, $field) use ($locale, $revision) {
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
                            ['term_id' => $term->id],
                            [
                                'content' => $subfield,
                                'locale' => 'sv'
                            ]
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
                    ['term_id' => $term->id],
                    [
                        'content' => $data,
                        'locale' => $locale
                    ]
                );
                return $definition;
            }

        });

        return $definitions;
    }

    public function getFieldContent($locale, $revision = 1)
    {
        $contentMap = $this->template->fields->mapWithKeys(function ($field) use ($locale, $revision) {
            $term = 'page_' . $this->id .'_'. $revision . '_' . $field->key;

            $content = I18nTerm::where('key', $term)
                ->whereHas('definitions', $definitions = function ($query) use ($locale) {
                    $query->where('locale', $locale);
                })
                ->with('definitions', $definitions)
                ->first();

            if (! $content) {
                return [$field['key'] => ''];
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
     * @param string $locale
     * @return void
     */
    public function publish(int $revision, string $locale = 'sv')
    {
        $content = $this->getFieldContent($locale, $revision);

        $oldRevision = $revision - 1;
        $this->published_version = $revision;
        $this->revision = $revision + 1;
        $this->published_at = now();
        $this->updateContent($content->toArray(), $locale, $this->revision);
        $this->save();

        $this->purgeOldRevisions($oldRevision);

        return $this;
    }
}