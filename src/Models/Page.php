<?php
namespace Infab\PageModule\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Infab\PageModule\Database\Factories\PageFactory;
use Infab\PageModule\Traits\HasTranslatedRevisions;
use Illuminate\Support\Str;

class Page extends Model
{
    use HasFactory, HasTranslatedRevisions;

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

    public function setTitleAttribute($value)
    {
        $this->attributes['title'] = $value;
        $this->attributes['slug'] = Str::slug($value);
    }
}
