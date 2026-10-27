<?php

return [
    'collection' => env('DIRECTORY_COLLECTION', 'choirs'),

    'fields' => [
        'title' => 'title',
        'city' => 'city',
        'description' => 'description',
        'visibility' => 'visibility',
        'hidden' => 'hidden',
        'lat' => 'lat',
        'lng' => 'lng',
    ],

    // Filter parameter (and projection column) => entry field. Columns are created by the
    // migration, so run `php artisan migrate` after adding a filter.
    'filters' => [
        'choir_type' => 'choir_type',
        'target_group' => 'target_group',
    ],

    'search' => [
        'per_page' => 20,
        'max_per_page' => 50,
        'max_radius_km' => 500,

        // 'sql' (default) searches the projection with LIKE; 'meilisearch' ranks the `q` text
        // search through Meilisearch (typo tolerance, synonyms). Without a host it stays on SQL.
        'driver' => env('DIRECTORY_SEARCH_DRIVER', 'sql'),
        'max_hits' => 1000,

        'meilisearch' => [
            'host' => env('MEILISEARCH_HOST'),
            'key' => env('MEILISEARCH_KEY'),
            'prefix' => env('DIRECTORY_MEILISEARCH_PREFIX', 'directory'),
            'timeout' => 3,
            'synonyms' => [
                'kammerchor' => ['kammerchoere', 'kammerchöre'],
                'gospelchor' => ['gospel'],
                'popchor' => ['pop-chor', 'pop chor'],
                'jugendchor' => ['jugend-chor', 'jugend chor'],
                'kinderchor' => ['kinder-chor', 'kinder chor'],
                'männerchor' => ['maennerchor', 'männer-chor'],
                'frauenchor' => ['frauen-chor'],
                'gemischter chor' => ['gemischtchor'],
                'strasse' => ['straße'],
            ],
        ],
    ],
];
