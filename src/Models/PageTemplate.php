<?php
namespace Infab\PageModule\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Infab\PageModule\Database\Factories\PageTemplateFactory;
use Infab\PageModule\Models\PageTemplateField;

class PageTemplate extends Model
{
    use HasFactory;

    public function __construct(array $attributes = [])
    {
        if (! isset($this->table)) {
            $this->setTable(config('page-module.page_templates_table_name'));
        }

        parent::__construct($attributes);
    }

    protected static function newFactory()
    {
        return PageTemplateFactory::new();
    }

    public function fields() : HasMany
    {
        return $this->hasMany(PageTemplateField::class, 'template_id')
            ->orderBy('sort_index');
    }
}
