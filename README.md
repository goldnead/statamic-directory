# Chorgesucht Directory

The Directory addon stores editable choir profiles as Statamic Entries and maintains a disposable SQL projection for public lookup. The first API slice supports public search by text or city, profile detail, and edits by a member authorized to update the profile's already-bound team.

## Data and privacy

Set `directory.collection` to the profile collection handle; it defaults to `choirs`. The public field mapping lives in `config/directory.php`. Public responses contain only title, city, and description. Email addresses, evidence, user IDs, and team/legacy bindings are never returned. Public access requires a published Entry, `visibility: public`, and a false `hidden` field. Reads re-check the canonical Entry and return its current whitelisted content, so stale visibility and content fail closed or remain current.

Profile rows are scoped by Statamic site. Requests use the current Statamic site by default and may specify a configured `site` query parameter. Search supports `q` (title or city) and exact `city` filters and returns at most 100 profiles. Radius and map search are not part of this slice.

Team binding is trusted import or moderation work. There is no public create, claim, or bind endpoint. A linked team can edit title, city, and description only when `Teams::can($actor, $team, 'update team')` returns true. Claim verification remains a future Teams extension.

Title, city, description, visibility, hidden, and publication columns are derived from Entries and can be refreshed in place. `team_id` and `legacy_id` are trusted bindings in the same SQL table; normal Entry sync never changes them, and database restore procedures must preserve or re-import them. Entry save and delete events update or remove rows idempotently. Flat-file saves and SQL writes do not share a transaction, and this slice has no automatic reconciliation job or rebuild command. After a failed projection update, re-save the Entry or call `ProfileProjection::sync($entry)` to retry; public reads continue to check canonical visibility and content. No existing legacy dataset is read by this addon.

## Routes

- `GET /directory/profiles?q=...&city=...&site=...` returns `{ "data": [...] }`.
- `GET /directory/profiles/{entryId}?site=...` returns `{ "data": {...} }`.
- `PATCH /directory/profiles/{entryId}?site=...` accepts `title`, `city`, and `description` from an authenticated authorized team member.

## Install

```bash
composer require goldnead/statamic-directory
php artisan migrate
```
