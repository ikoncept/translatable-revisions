<?php
namespace Infab\PageModule\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Infab\PageModule\Database\Factories\PageTemplateFieldFactory;

class PageTemplateField extends Model {

    use HasFactory;

    public $timestamps = false;

    public function __construct(array $attributes = [])
    {
        if (! isset($this->table)) {
            $this->setTable(config('page-module.page_template_fields_table_name'));
        }

        parent::__construct($attributes);
    }

    protected static function newFactory()
    {
        return PageTemplateFieldFactory::new();
    }
}