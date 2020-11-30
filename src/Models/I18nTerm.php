<?php

namespace Infab\TranslatableRevisions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Infab\TranslatableRevisions\Models\I18nDefinition;

class I18nTerm extends Model
{
    protected $fillable = ['key', 'description'];

    public function __construct(array $attributes = [])
    {
        if (! isset($this->table)) {
            $this->setTable(config('translatable-revisions.i18n_table_prefix_name') . 'i18n_terms');
        }

        parent::__construct($attributes);
    }

    /**
     * Definition relation
     *
     * @return HasMany
     */
    public function definitions() : HasMany
    {
        return $this->hasMany(I18nDefinition::class, 'term_id');
    }
}
