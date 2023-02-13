<?php

namespace Infab\TranslatableRevisions\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

class I18nTerm extends Model
{
    protected $fillable = ['key', 'description'];

    public function __construct(array $attributes = [])
    {
        if (! isset($this->table)) {
            $this->setTable(config('translatable-revisions.i18n_table_prefix_name').'i18n_terms');
        }

        parent::__construct($attributes);
    }

    /**
     * Definition relation
     *
     * @return HasMany
     */
    public function definitions(): HasMany
    {
        return $this->hasMany(I18nDefinition::class, 'term_id');
    }

    public function clearTermsWithKey(string $key): void
    {
        DB::table('i18n_terms')->whereRaw('i18n_terms.key LIKE ? ESCAPE ?', [$key.'%', '\\'])->delete();
    }

    protected function getTemplateJoinStatement(): Expression
    {
        return (get_class($this->getConnection()) === 'Illuminate\Database\SQLiteConnection')
            ? DB::raw("'%' || revision_template_fields.key || '%'")
            : DB::raw("concat('%-%-',revision_template_fields.key)");
    }

    public function scopeTranslatedFields(Builder $query, string $termWithoutKey, string $locale): Builder
    {
        return $query->leftJoin('i18n_definitions', 'i18n_terms.id', '=', 'i18n_definitions.term_id')
            ->leftJoin('revision_template_fields', 'i18n_terms.key', 'LIKE', $this->getTemplateJoinStatement())
            ->select(
                'i18n_terms.id', 'i18n_terms.key',
                'i18n_terms.id as term_id',
                'i18n_definitions.content',
                'revision_template_fields.repeater',
                'revision_template_fields.type',
                'revision_template_fields.translated',
                'revision_template_fields.key as template_key')
            ->whereRaw('i18n_terms.key LIKE ? ESCAPE ?', [$termWithoutKey.'%', '\\'])
            ->where('i18n_definitions.locale', $locale);
    }
}
