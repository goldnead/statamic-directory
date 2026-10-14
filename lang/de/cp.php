<?php

return [
    'nav' => 'Verzeichnis',
    'permission_view' => 'Verzeichnis ansehen',
    'permission_manage' => 'Verzeichnis verwalten',

    'description' => 'Profile, die aus den Verzeichnis-Collections projiziert wurden. Eine Zeile öffnet den kanonischen Eintrag.',
    'empty_heading' => 'Noch keine Profile im Verzeichnis',
    'setup_required' => 'Die Verzeichnis-Tabelle fehlt. Führe php artisan migrate aus.',
    'empty_rebuild_heading' => 'Projektion neu aufbauen',
    'empty_rebuild_description' => 'Liest jeden Verzeichniseintrag und projiziert ihn in die Suchtabelle.',

    'col_title' => 'Titel',
    'col_type' => 'Typ',
    'col_city' => 'Ort',
    'col_visibility' => 'Sichtbarkeit',
    'col_hidden' => 'Versteckt',
    'col_team' => 'Team',
    'col_coordinates' => 'Koordinaten',
    'col_synced' => 'Zuletzt synchronisiert',

    'filter_any' => 'Beliebig',
    'filter_type' => 'Typ',
    'filter_visibility' => 'Sichtbarkeit',
    'filter_hidden' => 'Versteckt',
    'filter_has_team' => 'Team verknüpft',
    'filter_has_coordinates' => 'Mit Koordinaten',
    'visibility_public' => 'Öffentlich',
    'visibility_private' => 'Privat',
    'yes' => 'Ja',
    'no' => 'Nein',

    'rebuild_all' => 'Projektion neu aufbauen',
    'rebuild_profile' => 'Profil neu aufbauen',
    'rebuild_queued' => 'Neuaufbau eingereiht.',
    'rebuild_already_running' => 'Ein Neuaufbau läuft bereits.',
    'rebuild_status_queued' => 'Neuaufbau eingereiht.',
    'rebuild_status_running' => 'Neuaufbau läuft: :processed von :total Profilen.',
    'rebuild_status_done' => 'Neuaufbau abgeschlossen: :processed Profile.',
    'rebuild_status_failed' => 'Neuaufbau fehlgeschlagen.',

    'unbind' => 'Team lösen',
    'unbind_confirm' => 'Das Team verliert die Verknüpfung zu diesem Profil und kann es nicht mehr bearbeiten. Das Profil kann erneut beansprucht werden. Das lässt sich nicht rückgängig machen.',
    'unbound' => 'Team von :title gelöst.',
    'not_bound' => ':title hat kein verknüpftes Team.',
    'cancel' => 'Abbrechen',

    'widget_total' => 'Profile',
    'widget_public' => 'Öffentlich',
    'widget_private' => 'Privat',
    'widget_no_coordinates' => 'Ohne Koordinaten',
    'widget_empty' => 'Noch keine Profile projiziert.',
];
