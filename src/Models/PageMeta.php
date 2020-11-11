<?php
namespace Infab\PageModule\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Infab\PageModule\Database\Factories\PageMetaFactory;

class PageMeta extends Model {

    use HasFactory;

    protected $fillable = ['meta_key', 'meta_value', 'page_id', 'page_version'];

    public function __construct(array $attributes = [])
    {
        if (! isset($this->table)) {
            $this->setTable(config('page-module.page_meta_table_name'));
        }

        parent::__construct($attributes);
    }

    protected static function newFactory()
    {
        return PageMetaFactory::new();
    }
}