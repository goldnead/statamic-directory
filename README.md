# Chorgesucht Directory

The Directory addon stores editable choir profiles as Statamic Entries and maintains a disposable SQL projection for public lookup. The first API slice supports public search by text or city, profile detail, and edits by a member authorized to update the profile's already-bound team.

## Data and privacy

Set `directory.collection` to the profile collection handle; it defaults to `choirs`. The public field mapping lives in `config/directory.php`. Public responses contain only title, city, and description. Email addresses, evidence, user IDs, and team/legacy bindings are never returned. Public access requires a published Entry, `visibility: public`, and a false `hidden` field. Reads re-check the canonical Entry and return its current whitelisted content, so stale visibility and content fail closed or remain current.

Profile rows are scoped by Statamic site. Requests use the current Statamic site; an optional `site` query parameter must match that current site. Other configured sites return 404, which prevents a public host from querying internal-site profiles. Search supports `q` (title or city) and exact `city` filters  Geo and filter search: `lat`, `lng` and `radius_km` (max `directory.search.max_radius_km`, default 500) restrict results to a radius (bounding box in SQL, exact Haversine distance in PHP) and sort by distance, which is included as `distance_km`; `lat`/`lng` without a radius only sorts. Entry fields `lat`/`lng` (configurable in `directory.fields`) fill the projection; profiles without valid coordinates are excluded from location searches. Filters in `directory.filters` (default `choir_type`, `target_group`) map a parameter to an Entry field and an indexed projection column; run `php artisan migrate` after changing them. `page` and `per_page` (default 20, max 50) paginate, and the JSON response carries `meta` with `page`, `per_page`, `total`, `last_page`. Because pages are cut from the index and then re-checked against the current Entry, a page can be shorter than `per_page`. Map display is not part of this slice.

Antlers: `{{ directory:search lat="52.5" lng="13.4" radius_km="30" q="" city="" choir_type="" page="1" per_page="20" }}{{ profiles }}...{{ /profiles }}{{ /directory:search }}` exposes `profiles`, `page`, `per_page`, `total`, `last_page`, `no_results`. Invalid parameters yield `no_results`.

Team binding is owned by Teams' claim lifecycle. There is no Directory public create, claim, or bind endpoint. A linked team can edit title, city, and description only when `Teams::can($actor, $team, 'update team')` returns true.

## Multiple profile types

Besides the single `directory.collection` handle, `directory.collections` can list several collections, each with its own field mapping and filters (missing keys fall back to the global `fields` and `filters`):

```php
'collections' => [
    'choirs' => ['filters' => ['choir_type' => 'choir_type']],
    'leaders' => [
        'fields' => ['title' => 'name', 'city' => 'town'],
        'filters' => ['focus' => 'focuses'],
    ],
],
```

The first entry is the default type. Without `directory.collections` the old `directory.collection` format works unchanged. The projection stores the handle in a `collection` column (existing rows get the first collection; run `php artisan migrate`), and filter columns are shared across collections. Search, `GET /directory/profiles?type=leaders` and `{{ directory:search type="leaders" }}` take a `type` parameter; without it the first collection is searched. Filter parameters of other types are ignored, an unknown `type` is invalid. Visibility, hidden state, claim adapter and team binding apply per collection as before. Public data now also carries `type`.

## Teams claim adapter

`Goldnead\Directory\Claims\DirectoryClaimResource` implements the optional Teams claim resource contract for approved directory claims. Register it as `teams.claims.resources.directory`. It binds only an unbound profile on the current Statamic site whose canonical Entry remains in the configured collection and is not hidden. Private imported drafts may be claimed; the adapter does not publish or modify Entry fields. Teams owns actor authorization and the surrounding transaction.

Title, city, description, visibility, hidden, and publication columns are derived from Entries and can be refreshed in place. `team_id` and `legacy_id` are trusted bindings in the same SQL table; normal Entry sync never changes them, and database restore procedures must preserve or re-import them. Entry save and delete events update or remove rows idempotently. Flat-file saves and SQL writes do not share a transaction, and this slice has no scheduled reconciliation. After a failed projection update, re-save the Entry, call `ProfileProjection::sync($entry)` or run `php artisan directory:rebuild` to retry; public reads continue to check canonical visibility and content. No existing legacy dataset is read by this addon.

## Control Panel

The nav item **Directory** (Tools section, translatable, English source strings with a German translation) lists the projected profiles of every configured type in core's native listing. Columns: title, type, city, visibility, hidden, team bound, coordinates, last synced. Search matches title and city. Filters: type, visibility, hidden, team bound, has coordinates. A title links to the canonical Entry edit screen.

Permissions: `view directory` opens the listing, status and widget; `manage directory` (a child of `view directory`) allows the actions below. Without them the CP answers 403 (JSON) or redirects to the dashboard with an error (HTML).

- **Rebuild projection** (header button, `manage directory`) queues the `RebuildProjection` job for all entries; a row action rebuilds one profile. The page shows queued, running (with progress) and finished status and refreshes itself while a job runs. Only one rebuild runs at a time.
- **Unbind team** (row action, destructive, asks for confirmation, `manage directory`) clears `team_id` so the profile can be claimed again. It is written to the log and, when `goldnead/statamic-activity` is installed, to the activity ledger as `directory.profile_team_unbound`.
- **Dashboard widget** `directory` (add it in `config/statamic/cp.php` or via the dashboard) shows per type: total, public, private and profiles without coordinates.

The same rebuild is available on the command line:

```bash
php artisan directory:rebuild                  # all types, synchronous
php artisan directory:rebuild --collection=leaders
php artisan directory:rebuild --entry=<id>
php artisan directory:rebuild --queue          # as a queued job
php artisan directory:rebuild --prune          # also drop rows whose Entry is gone
```

A rebuild only rewrites derived columns; `team_id` and `legacy_id` are never touched. The built Control Panel bundle lives in `resources/dist` and is committed; rebuild it with `npm install && npm run build` after changing `resources/js`.

## Meilisearch (optional)

Set `DIRECTORY_SEARCH_DRIVER=meilisearch`, `MEILISEARCH_HOST` and optionally `MEILISEARCH_KEY` for typo-tolerant `q` search. **Run a complete `php artisan directory:rebuild` first, switch the driver afterwards.** A partly filled index returns real but incomplete results; only a completely empty index falls back to SQL. Without a host, or while Meilisearch is unreachable (60 s marker), search uses SQL.

## Routes

- `GET /directory/profiles?q=...&city=...&lat=...&lng=...&radius_km=...&page=...&per_page=...&site=...` returns `{ "data": [...] }`.
- `GET /directory/profiles/{entryId}?site=...` returns `{ "data": {...} }`.
- `PATCH /directory/profiles/{entryId}?site=...` accepts `title`, `city`, and `description` from an authenticated authorized team member.

## Requirements

PHP 8.3 or newer, Statamic 6 and `goldnead/statamic-teams`.

## Install

```bash
composer require goldnead/statamic-directory
php artisan migrate
```

## Usage

Create the profile collections, list them in `directory.collections`, then use the Control Panel, the routes above or the `{{ directory:search }}` tag. Run `php artisan directory:rebuild` once to project existing entries.
