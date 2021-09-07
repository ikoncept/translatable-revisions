<?php

return [
    'revisions_table_name' => env('TRANSLATABLE_REVISIONS_TABLE', 'pages'),
    'revision_templates_table_name' => env('TRANSLATABLE_REVISION_TEMPLATES_TABLE', 'revision_templates'),
    'revision_meta_table_name' => env('TRANSLATABLE_REVISIONS_META_TABLE', 'revision_meta'),
    'revision_template_fields_table_name' => env('TRANSLATABLE_REVISION_TEMPLATE_FIELDS_TABLE', 'revision_template_fields'),
    'i18n_table_prefix_name' => env('TRANSLATABLE_REVISIONS_I18N_TABLE_PREFIX_NAME', ''),
    'supportedLocales' => [
        'en' => ['name' => 'English', 'script' => 'Latn', 'native' => 'English', 'regional' => 'en_GB'],
        'sv' => ['name' => 'Swedish', 'script' => 'Latn', 'native' => 'svenska', 'regional' => 'sv_SE']
    ],
    'delimiter' => env('TRANSLATABLE_REVISIONS_DELIMITER', '-')
];