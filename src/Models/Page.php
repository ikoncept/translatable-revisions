<?php
namespace Infab\PageModule\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Infab\PageModule\Database\Factories\PageFactory;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Infab\PageModule\Traits\HasRevisions;

class Page extends Model
{
    use HasFactory, HasSlug, HasRevisions;

    protected static function newFactory()
    {
        return PageFactory::new();
    }

    protected $dates = ['published_at'];

    public function __construct(array $attributes = [])
    {
        if (! isset($this->table)) {
            $this->setTable(config('page-module.pages_table_name'));
        }

        parent::__construct($attributes);
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

}
