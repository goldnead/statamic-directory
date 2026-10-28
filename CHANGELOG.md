# Changelog

## Unreleased

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
