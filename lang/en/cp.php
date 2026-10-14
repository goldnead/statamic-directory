<?php

return [
    'nav' => 'Directory',
    'permission_view' => 'View directory',
    'permission_manage' => 'Manage directory',

    'description' => 'Profiles projected from your directory collections. Open a row to edit the canonical entry.',
    'empty_heading' => 'No profiles in the directory yet',
    'setup_required' => 'The directory table is missing. Run php artisan migrate.',
    'empty_rebuild_heading' => 'Rebuild the projection',
    'empty_rebuild_description' => 'Reads every directory entry and projects it into the lookup table.',

    'col_title' => 'Title',
    'col_type' => 'Type',
    'col_city' => 'City',
    'col_visibility' => 'Visibility',
    'col_hidden' => 'Hidden',
    'col_team' => 'Team',
    'col_coordinates' => 'Coordinates',
    'col_synced' => 'Last synced',

    'filter_any' => 'Any',
    'filter_type' => 'Type',
    'filter_visibility' => 'Visibility',
    'filter_hidden' => 'Hidden',
    'filter_has_team' => 'Team bound',
    'filter_has_coordinates' => 'Has coordinates',
    'visibility_public' => 'Public',
    'visibility_private' => 'Private',
    'yes' => 'Yes',
    'no' => 'No',

    'rebuild_all' => 'Rebuild projection',
    'rebuild_profile' => 'Rebuild profile',
    'rebuild_queued' => 'Rebuild queued.',
    'rebuild_already_running' => 'A rebuild is already running.',
    'rebuild_status_queued' => 'Rebuild queued.',
    'rebuild_status_running' => 'Rebuild running: :processed of :total profiles.',
    'rebuild_status_done' => 'Rebuild finished: :processed profiles.',
    'rebuild_status_failed' => 'Rebuild failed.',

    'unbind' => 'Unbind team',
    'unbind_confirm' => 'The team loses its link to this profile and can no longer edit it. The profile can be claimed again. This cannot be undone.',
    'unbound' => 'Team unbound from :title.',
    'not_bound' => ':title has no team bound.',
    'cancel' => 'Cancel',

    'widget_total' => 'Profiles',
    'widget_public' => 'Public',
    'widget_private' => 'Private',
    'widget_no_coordinates' => 'No coordinates',
    'widget_empty' => 'No profiles projected yet.',
];
