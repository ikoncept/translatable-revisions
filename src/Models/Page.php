<?php
namespace Infab\PageModule\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Infab\PageModule\Database\Factories\PageFactory;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Infab\PageModule\Models\I18nTerm;

class Page extends Model
{
    use HasFactory, HasSlug;


    protected $dates = ['published_at'];

    public function __construct(array $attributes = [])
    {
        if (! isset($this->table)) {
            $this->setTable(config('page-module.pages_table_name'));
        }

        parent::__construct($attributes);
    }

    protected static function newFactory()
    {
        return PageFactory::new();
    }

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions() : SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug');
    }

    public function template() : BelongsTo
    {
        return $this->belongsTo(PageTemplate::class, 'template_id', 'id');
    }

    public function meta() : HasMany
    {
        return $this->hasMany(PageMeta::class);
    }

    public function deleteContent()
    {
        $identifier = 'page_' . $this->id .'_'. $this->revision . '_' ;

        I18nTerm::where('key', 'LIKE', $identifier . '%')->delete();
    }

    /**
     * Update content for multiple fields
     *
     * @param array $fieldData
     * @param string $locale
     * @return Collection
     */
    public function updateContent(array $fieldData, string $locale = 'sv') : Collection
    {
        $definitions = collect($fieldData)->map(function ($data, $field) use ($locale) {
            $templateField = PageTemplateField::where('key', $field)->first();
            $identifier = 'page_' . $this->id .'_'. $this->revision . '_' . $field;

            // Find term
            $term = I18nTerm::updateOrCreate(
                ['key' => $identifier],
                ['description' => $templateField->name]
            );

            // Create or update definition
            $definition = I18nDefinition::updateOrCreate(
                ['term_id' => $term->id],
                [
                    'content' => $data,
                    'locale' => $locale
                ]
            );

            return $definition;
        });

        return $definitions;
    }

    public function getFieldContent($locale)
    {

        $contentMap = $this->template->fields->mapWithKeys(function($field) use ($locale) {
            $term = 'page_' . $this->id .'_'. $this->revision . '_' . $field->key;

            $content = I18nTerm::where('key', $term)
                ->whereHas('definitions', $definitions = function($query) use ($locale) {
                    $query->where('locale', $locale);
                })
                ->with('definitions', $definitions)
                ->first();

            if(! $content) {
                return [$field['key'] => ''];
            }
            return [
                $field['key'] => $content->definitions->first()->content
            ];
        });

        return $contentMap;
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
        $versionToPublish = Page::where('revision', $revision)->firstOrFail();
        $content = $versionToPublish->getFieldContent($locale);

        $this->published_version = $versionToPublish->revision;
        $this->revision = $versionToPublish->revision + 1;
        $this->published_at = now();
        $this->title = $versionToPublish->title;
        $this->updateContent($content->toArray());
        $this->save();

        $versionToPublish->deleteContent();
        $versionToPublish->delete();

        return $this;
    }
}
