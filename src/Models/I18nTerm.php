<?php

namespace Infab\PageModule\Models;

use Illuminate\Database\Eloquent\Model;
use Infab\PageModule\Models\I18nDefinition;

class I18nTerm extends Model
{
    protected $fillable = ['key', 'description'];

    public function __construct(array $attributes = [])
    {
        if (! isset($this->table)) {
            $this->setTable(config('page-module.i18n_table_prefix_name') . 'i18n_terms');
        }

        parent::__construct($attributes);
    }

    public function definitions()
    {
        return $this->hasMany(I18nDefinition::class, 'term_id');
    }
}
