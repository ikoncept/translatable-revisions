<?php
namespace Infab\TranslatableRevisions\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Infab\TranslatableRevisions\Database\Factories\RevisionMetaFactory;

class RevisionMeta extends Model
{
    use HasFactory;

    protected $fillable = ['meta_key', 'meta_value', 'model_id', 'model_type', 'model_version'];

    protected $casts = [
        'meta_value' => 'array'
    ];

    public function __construct(array $attributes = [])
    {
        if (! isset($this->table)) {
            $this->setTable(config('translatable-revisions.revision_meta_table_name'));
        }

        parent::__construct($attributes);
    }

    /**
     * Creat a new factory
     *
     * @return RevisionMetaFactory
     */
    protected static function newFactory()
    {
        return RevisionMetaFactory::new();
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeModelMeta(Builder $query, Model $model) : Builder
    {
        return $query->where('model_type', $model->morphClass ?? $model->getMorphClass())
            ->where('model_id', $model->id);
    }

    public function scopeMetaFields(Builder $query, int $revision) : Builder
    {
        return $query->leftJoin('revision_template_fields', 'revision_meta.meta_key', '=', 'revision_template_fields.key')
            ->select(
                'revision_meta.*',
                'revision_template_fields.type'
            )
            ->where('model_version', $revision);
    }

}
