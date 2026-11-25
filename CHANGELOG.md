# Changelog

## Unreleased

### Fixed

- `directory:rebuild --prune` refuses to run when a collection still has profile rows but no entries, instead of deleting their rows and bindings (`team_id`, `legacy_id`).
- One entry that cannot be projected no longer stops the rebuild: it is logged and counted (`errors` in the status, exit code 1 on the command), the entries after it are still rebuilt, and its row survives `--prune`.
- A rebuild status that is `queued` or `running` and older than 15 minutes is treated as orphaned, so a dead worker no longer blocks new rebuilds.
- A rebuild that cannot be queued is reported as failed in the CP toast and in the command output, instead of failing silently.
- Invalid `directory:search` tag parameters are logged as a warning; the tag still renders no results.

## 0.2.1 — 2026-11-16

### Changed

- `composer.json` requires `goldnead/statamic-teams` `^0.5` (was `^0.4`). Composer treats a 0.x minor as breaking, so `^0.4` did not accept teams 0.5.0 and a site could not install both. No code change: teams 0.5 only adds (more than one role per member, new events and a migration), the directory uses no part of it that changed.

### Upgrading

- Update `goldnead/statamic-teams` to 0.5 and run `php artisan migrate` for its new tables.

## 0.2.0 — 2026-10-28

### Added

- Optional Meilisearch driver for the `q` text search (typo tolerance, relevance, synonyms). Set `DIRECTORY_SEARCH_DRIVER=meilisearch` and `MEILISEARCH_HOST` (`MEILISEARCH_KEY`, `DIRECTORY_MEILISEARCH_PREFIX` optional), then run `php artisan directory:rebuild` to create the indexes and fill them. Talks to the Meilisearch REST API through Laravel's HTTP client, no new package. Only public, published, visible profiles are indexed (title, city); visibility is checked again against the Entry on output. Without a host, or when Meilisearch fails, search stays on SQL. Switch to `meilisearch` only after a complete `php artisan directory:rebuild`: an index that is only partly filled returns real but incomplete results (only a completely empty index falls back to SQL).

## 0.1.1 — 2026-10-15

### Fixed

- `goldnead/statamic-teams` is required as `^0.4` (0.1.0 asked for `^0.3`, which has no claim adapter).

## 0.1.0 — 2026-10-15

- Control Panel: "Directory" nav item with a native listing of projected profiles (search, filters for type, visibility, hidden, team bound, coordinates), rebuild actions, unbind team action and a dashboard widget.
- Permissions `view directory` and `manage directory`.
- `php artisan directory:rebuild` (`--collection`, `--entry`, `--prune`, `--queue`) and the queued `RebuildProjection` job.
- German and English CP strings.
