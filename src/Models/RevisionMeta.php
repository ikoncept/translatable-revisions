<?php
namespace Infab\TranslatableRevisions\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Infab\TranslatableRevisions\Database\Factories\RevisionMetaFactory;

class RevisionMeta extends Model
{
    use HasFactory;

    protected $fillable = ['meta_key', 'meta_value', 'model_id', 'model_type', 'model_version'];

    public function __construct(array $attributes = [])
    {
        if (! isset($this->table)) {
            $this->setTable(config('translatable-revisions.revision_meta_table_name'));
        }

        parent::__construct($attributes);
    }

    protected static function newFactory()
    {
        return RevisionMetaFactory::new();
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
