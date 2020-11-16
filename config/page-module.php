<?php

return [
    'pages_table_name' => env('PAGE_MODULE_PAGES_TABLE', 'pages'),
    'page_templates_table_name' => env('PAGE_MODULE_PAGE_TEMPLATES_TABLE', 'page_templates'),
    'page_meta_table_name' => env('PAGE_MODULE_PAGE_META_TABLE', 'page_meta'),
    'page_template_fields_table_name' => env('PAGE_MODULE_PAGE_TEMPLATE_FIELDS_TABLE', 'page_template_fields'),
    'i18n_table_prefix_name' => env('PAGE_MODULE_I18N_TABLE_PREFIX_NAME', ''),
    'supportedLocales' => [
        'en' => ['name' => 'English', 'script' => 'Latn', 'native' => 'English', 'regional' => 'en_GB'],
        'sv' => ['name' => 'Swedish', 'script' => 'Latn', 'native' => 'svenska', 'regional' => 'sv_SE']
    ]
];