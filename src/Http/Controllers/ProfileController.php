<?php

namespace Goldnead\Directory\Http\Controllers;

use Goldnead\Directory\Models\DirectoryProfile;
use Goldnead\Directory\Services\ProfileSearch;
use Goldnead\Teams\Facades\Teams;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Statamic\Facades\Site;

class ProfileController extends Controller
{
    public function __construct(private ProfileSearch $search) {}

    private const PROTECTED_FIELDS = [
        'team_id', 'teamId', 'hidden', 'visibility', 'owner', 'owner_id',
        'owner_user_id', 'user', 'user_id', 'userId', 'email', 'contact_email',
        'evidence', 'legacy_id', 'legacyId',
    ];

    public function index(Request $request): JsonResponse
    {
        $params = $request->validate(ProfileSearch::rules());

        return response()->json($this->search->search($params, $this->siteHandle($request)));
    }

    public function show(Request $request, string $entryId): JsonResponse
    {
        $profile = DirectoryProfile::query()
            ->where('entry_id', $entryId)
            ->where('site', $this->siteHandle($request))
            ->first();

        abort_unless($profile && ($entry = $this->search->publicEntry($profile)), 404);

        return response()->json(['data' => $this->search->publicData($profile, $entry)]);
    }

    public function update(Request $request, string $entryId): JsonResponse
    {
        abort_unless($request->user(), 403);

        $profile = DirectoryProfile::query()
            ->where('entry_id', $entryId)
            ->where('site', $this->siteHandle($request))
            ->firstOrFail();

        $entry = $this->search->canonicalEntry($profile);
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

        return response()->json(['data' => $this->search->publicData($profile, $entry)]);
    }

    private function siteHandle(Request $request): string
    {
        $request->validate(['site' => ['sometimes', 'string', 'max:64']]);
        $currentSite = Site::current()->handle();
        $site = $request->query('site', $currentSite);

        abort_unless($site === $currentSite, 404);

        return $site;
    }
}
