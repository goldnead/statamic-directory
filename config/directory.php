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
    ],
];
