# Changelog

## 0.1.1 — 2026-10-15

### Fixed

- `goldnead/statamic-teams` is required as `^0.4` (0.1.0 asked for `^0.3`, which has no claim adapter).

## 0.1.0 — 2026-10-15

- Control Panel: "Directory" nav item with a native listing of projected profiles (search, filters for type, visibility, hidden, team bound, coordinates), rebuild actions, unbind team action and a dashboard widget.
- Permissions `view directory` and `manage directory`.
- `php artisan directory:rebuild` (`--collection`, `--entry`, `--prune`, `--queue`) and the queued `RebuildProjection` job.
- German and English CP strings.
