<?php
namespace Infab\PageModule\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Infab\PageModule\Database\Factories\PageFactory;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Page extends Model {

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
}