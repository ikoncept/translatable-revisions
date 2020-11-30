<?php
namespace Infab\TranslatableRevisions\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Infab\TranslatableRevisions\Database\Factories\RevisionTemplateFieldFactory;

class RevisionTemplateField extends Model {

    use HasFactory;

    public $timestamps = false;

    public function __construct(array $attributes = [])
    {
        if (! isset($this->table)) {
            $this->setTable(config('translatable-revisions.revision_template_fields_table_name'));
        }

        parent::__construct($attributes);
    }

    /**
     * Create a new factory
     *
     * @return RevisionTemplateFieldFactory
     */
    protected static function newFactory()
    {
        return RevisionTemplateFieldFactory::new();
    }
}