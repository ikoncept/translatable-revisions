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

    /**
     * Update content for a field
     *
     * @param array $fieldData
     * @return Collection
     */
    public function updateContent(array $fieldData) : Collection
    {
        $locale = 'sv';
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
}
