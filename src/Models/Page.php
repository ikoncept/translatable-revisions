<?php
namespace Infab\TranslatableRevisions\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Infab\TranslatableRevisions\Database\Factories\PageFactory;
use Infab\TranslatableRevisions\Traits\HasTranslatedRevisions;
use Illuminate\Support\Str;
use Infab\TranslatableRevisions\Traits\RevisionOptions;

class Page extends Model
{
    use HasFactory, HasTranslatedRevisions;

    /**
     * Get the options for the revisions.
     */
    public function getRevisionOptions() : RevisionOptions
    {
        return RevisionOptions::create()
            ->registerSpecialTypes(['image'])
            ->registerGetters([
                'image' => 'getImages',
                'repeater' => 'getRepeater'
            ])
            ->registerCacheTagsToFlush(['cms_pages']);
    }

    /**
     * Get images
     *
     * @param RevisionMeta|null $meta
     * @return Collection
     */
    public function getImages(RevisionMeta $meta = null) : Collection
    {
        return collect($meta);
    }

    /**
     * Create a new factory
     *
     * @return PageFactory
     */
    protected static function newFactory()
    {
        return PageFactory::new();
    }


    protected $dates = ['published_at'];

    public function __construct(array $attributes = [])
    {
        if (! isset($this->table)) {
            $this->setTable(config('translatable-revisions.revisions_table_name'));
        }

        parent::__construct($attributes);
    }

    /**
     * Set the title attribute/slug
     *
     * @param mixed $value
     * @return void
     */
    public function setTitleAttribute($value)
    {
        $this->attributes['title'] = $value;
        $this->attributes['slug'] = Str::slug($value);
    }
}
