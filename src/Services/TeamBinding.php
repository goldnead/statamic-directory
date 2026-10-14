<?php

namespace Goldnead\Directory\Services;

use Goldnead\Directory\Models\DirectoryProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Releases the team binding that DirectoryClaimResource::bind() set. The profile row stays;
 * only `team_id` is cleared, so the profile can be claimed again.
 */
class TeamBinding
{
    public const ACTIVITY_FACADE = 'Goldnead\Activity\Facades\Activity';

    /** @return bool false when the profile was not bound */
    public function unbind(DirectoryProfile $profile, mixed $actor = null): bool
    {
        $previous = DB::transaction(function () use ($profile): ?int {
            $row = DirectoryProfile::query()->lockForUpdate()->find($profile->getKey());

            if (! $row || $row->team_id === null) {
                return null;
            }

            $previous = $row->team_id;
            $row->forceFill(['team_id' => null])->save();

            return $previous;
        });

        if ($previous === null) {
            return false;
        }

        $this->audit($profile, $previous, $actor);

        return true;
    }

    private function audit(DirectoryProfile $profile, int $teamId, mixed $actor): void
    {
        $properties = [
            'entry_id' => $profile->entry_id,
            'site' => $profile->site,
            'collection' => $profile->collection,
            'team_id' => $teamId,
            'actor' => is_object($actor) && method_exists($actor, 'id') ? (string) $actor->id() : null,
        ];

        Log::info('statamic-directory: team unbound from profile.', $properties);

        $facade = self::ACTIVITY_FACADE;
        if (! class_exists($facade)) {
            return;
        }

        try {
            $facade::record('directory.profile_team_unbound', [
                'subject_type' => 'directory_profile',
                'subject_id' => (string) $profile->getKey(),
                'source' => 'statamic-directory',
                'properties' => $properties,
            ]);
        } catch (Throwable $e) {
            Log::warning('statamic-directory: writing the activity failed.', ['exception' => $e->getMessage()]);
        }
    }
}
