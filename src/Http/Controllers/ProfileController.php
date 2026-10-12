<?php

namespace Goldnead\Directory\Http\Controllers;

use Goldnead\Directory\Models\DirectoryProfile;
use Goldnead\Teams\Facades\Teams;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;

class ProfileController extends Controller
{
    private const PROTECTED_FIELDS = [
        'team_id', 'teamId', 'hidden', 'visibility', 'owner', 'owner_id',
        'owner_user_id', 'user', 'user_id', 'userId', 'email', 'contact_email',
        'evidence', 'legacy_id', 'legacyId',
    ];

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['sometimes', 'string', 'max:255'],
            'city' => ['sometimes', 'string', 'max:255'],
            'site' => ['sometimes', 'string', 'max:64'],
        ]);

        $site = $this->siteHandle($request);
        $query = DirectoryProfile::query()
            ->where('site', $site)
            ->where('published', true)
            ->where('hidden', false)
            ->where('visibility', 'public');

        if ($request->filled('city')) {
            $query->whereRaw('LOWER(city) = ?', [mb_strtolower(trim($request->string('city')->toString()))]);
        }

        if ($request->filled('q')) {
            $term = '%'.mb_strtolower(trim($request->string('q')->toString())).'%';
            $query->where(function ($builder) use ($term): void {
                $builder->whereRaw('LOWER(title) LIKE ?', [$term])->orWhereRaw('LOWER(city) LIKE ?', [$term]);
            });
        }

        $data = $query->orderBy('title')->limit(100)->get()
            ->map(function (DirectoryProfile $profile) use ($request): ?array {
                $entry = $this->publicCanonicalEntry($profile);

                if (! $entry) {
                    return null;
                }

                $data = $this->publicData($profile, $entry);

                if ($request->filled('city') && mb_strtolower(trim((string) $data['city'])) !== mb_strtolower(trim($request->string('city')->toString()))) {
                    return null;
                }

                if ($request->filled('q')) {
                    $needle = mb_strtolower(trim($request->string('q')->toString()));
                    $haystack = mb_strtolower((string) $data['title'].' '.(string) $data['city']);

                    if (! str_contains($haystack, $needle)) {
                        return null;
                    }
                }

                return $data;
            })
            ->filter()
            ->values();

        return response()->json(['data' => $data]);
    }

    public function show(Request $request, string $entryId): JsonResponse
    {
        $profile = DirectoryProfile::query()
            ->where('entry_id', $entryId)
            ->where('site', $this->siteHandle($request))
            ->first();

        abort_unless($profile && ($entry = $this->publicCanonicalEntry($profile)), 404);

        return response()->json(['data' => $this->publicData($profile, $entry)]);
    }

    public function update(Request $request, string $entryId): JsonResponse
    {
        abort_unless($request->user(), 403);

        $profile = DirectoryProfile::query()
            ->where('entry_id', $entryId)
            ->where('site', $this->siteHandle($request))
            ->firstOrFail();

        $entry = $this->canonicalEntry($profile);
        abort_unless($entry, 404);
        abort_unless($profile->team_id, 403);

        $team = Teams::find($profile->team_id);
        abort_unless($team && Teams::can($request->user(), $team, 'update team'), 403);

        if (array_intersect(self::PROTECTED_FIELDS, array_keys($request->all())) !== []) {
            return response()->json(['message' => __('directory::messages.protected_fields')], 422);
        }

        $validator = Validator::make($request->all(), [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ]);

        $validated = $validator->validate();
        abort_if($validated === [], 422, __('directory::messages.editable_fields_required'));

        $fieldMapping = config('directory.fields');
        foreach ($validated as $field => $value) {
            $entry->set($fieldMapping[$field] ?? $field, $value);
        }

        $entry->save();

        return response()->json(['data' => $this->publicData($profile, $entry)]);
    }

    private function siteHandle(Request $request): string
    {
        $request->validate(['site' => ['sometimes', 'string', 'max:64']]);
        $currentSite = Site::current()->handle();
        $site = $request->query('site', $currentSite);

        abort_unless($site === $currentSite, 404);

        return $site;
    }

    private function publicCanonicalEntry(DirectoryProfile $profile): ?EntryContract
    {
        if (! $profile->published || $profile->hidden || $profile->visibility !== 'public') {
            return null;
        }

        $entry = $this->canonicalEntry($profile);

        if (! $entry || ! $entry->published()) {
            return null;
        }

        $fields = config('directory.fields');
        $data = $entry->data()->all();

        if (($data[$fields['visibility']] ?? null) !== 'public' || (bool) ($data[$fields['hidden']] ?? false)) {
            return null;
        }

        return $entry;
    }

    private function canonicalEntry(DirectoryProfile $profile): ?EntryContract
    {
        $entry = Entry::find($profile->entry_id);
        $entry = $entry?->in($profile->site);

        if (! $entry || (string) $entry->id() !== (string) $profile->entry_id
            || (string) $entry->locale() !== (string) $profile->site
            || $entry->collectionHandle() !== config('directory.collection')) {
            return null;
        }

        return $entry;
    }

    private function publicData(DirectoryProfile $profile, EntryContract $entry): array
    {
        $fields = config('directory.fields');
        $source = $entry->data()->all();

        return [
            'id' => $profile->entry_id,
            'site' => $profile->site,
            'title' => $source[$fields['title']] ?? $entry->get('title'),
            'city' => $source[$fields['city']] ?? null,
            'description' => $source[$fields['description']] ?? null,
        ];
    }
}
