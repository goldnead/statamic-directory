<?php

namespace Goldnead\Directory\Http\Controllers\Cp;

use Goldnead\Directory\Jobs\RebuildProjection;
use Goldnead\Directory\Models\DirectoryProfile;
use Goldnead\Directory\Scopes\DirectoryFilter;
use Goldnead\Directory\Services\Collections;
use Goldnead\Directory\Services\DirectoryStats;
use Goldnead\Directory\Services\TeamBinding;
use Goldnead\Directory\Support\RebuildStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Statamic\CP\Column;
use Statamic\Facades\CP\Toast;
use Statamic\Facades\Entry;
use Statamic\Facades\Scope;
use Statamic\Facades\User;
use Statamic\Http\Requests\FilteredRequest;
use Statamic\Query\Scopes\Filters\Concerns\QueriesFilters;

/**
 * Control Panel for the projection. `index()` answers both the Inertia page and the JSON the
 * native <Listing> re-fetches on every search, sort, filter and page change.
 */
class DirectoryController extends Controller
{
    use QueriesFilters;

    /** Sorting is whitelisted: `sort` reaches an ORDER BY, where a column name is not a bound parameter. */
    private const SORTABLE = ['title', 'collection', 'city', 'visibility', 'hidden', 'updated_at'];

    private const MAX_PER_PAGE = 200;

    public function index(FilteredRequest $request)
    {
        Gate::authorize('view directory');

        // A fresh install answers an empty listing instead of a 500 until `php artisan migrate` ran.
        $ready = Schema::hasTable('directory_profiles');
        if (! $ready) {
            Log::warning('statamic-directory: the directory_profiles table is missing, run php artisan migrate.');
        }

        if ($request->wantsJson()) {
            return $ready ? $this->listing($request) : $this->emptyListing();
        }

        return Inertia::render('directory::Index', [
            'columns' => collect($this->columns())->map->toArray()->all(),
            'filters' => Scope::filters(DirectoryFilter::LISTING_KEY),
            'listingUrl' => cp_route('directory.index'),
            'rebuildUrl' => cp_route('directory.rebuild'),
            'statusUrl' => cp_route('directory.rebuild-status'),
            'rebuild' => RebuildStatus::get(),
            'canManage' => $this->canManage(),
            'perPage' => $this->perPage(null),
            'hasAny' => $ready && DirectoryProfile::query()->exists(),
            'setupRequired' => ! $ready,
        ]);
    }

    /** @return array<string, mixed> */
    private function emptyListing(): array
    {
        return [
            'data' => [],
            'meta' => [
                'columns' => collect($this->columns())->map->toArray()->all(),
                'activeFilterBadges' => [],
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $this->perPage(null),
                'from' => null,
                'to' => null,
                'total' => 0,
            ],
        ];
    }

    public function status(): JsonResponse
    {
        Gate::authorize('view directory');

        return response()->json(['rebuild' => RebuildStatus::get()]);
    }

    public function rebuild(): RedirectResponse
    {
        Gate::authorize('manage directory');

        return $this->queueRebuild(null, null);
    }

    public function rebuildProfile(int $id): RedirectResponse
    {
        Gate::authorize('manage directory');

        $profile = DirectoryProfile::query()->findOrFail($id);

        return $this->queueRebuild($profile->collection, $profile->entry_id);
    }

    public function unbind(int $id, TeamBinding $binding): RedirectResponse
    {
        Gate::authorize('manage directory');

        $profile = DirectoryProfile::query()->findOrFail($id);

        if ($binding->unbind($profile, User::current())) {
            Toast::success(__('directory::cp.unbound', ['title' => $profile->title]));
        } else {
            Toast::error(__('directory::cp.not_bound', ['title' => $profile->title]));
        }

        return back();
    }

    private function queueRebuild(?string $collection, ?string $entryId): RedirectResponse
    {
        if (RebuildStatus::running()) {
            Toast::info(__('directory::cp.rebuild_already_running'));

            return back();
        }

        RebuildStatus::put('queued', ['scope' => $entryId !== null ? 'entry' : ($collection ?? 'all')]);
        RebuildProjection::dispatch($collection, $entryId);

        Toast::success(__('directory::cp.rebuild_queued'));

        return back();
    }

    /** @return array<string, mixed> */
    private function listing(FilteredRequest $request): array
    {
        $query = DirectoryProfile::query();

        $badges = $this->queryFilters(
            $query,
            $request->filters ?? [],
            ['handle' => DirectoryFilter::LISTING_KEY],
        );

        $this->applySearch($query, (string) $request->input('search', ''));

        $sort = in_array($request->input('sort'), self::SORTABLE, true) ? $request->input('sort') : 'title';
        $order = $request->input('order') === 'desc' ? 'desc' : 'asc';

        $paginator = $query
            ->orderBy($sort, $order)
            ->orderBy('id', $order)
            ->paginate($this->perPage($request->input('perPage')))
            ->withQueryString();

        $canManage = $this->canManage();

        return [
            'data' => collect($paginator->items())->map(fn (DirectoryProfile $p) => $this->row($p, $canManage))->all(),
            'meta' => [
                'columns' => collect($this->columns())->map->toArray()->all(),
                'activeFilterBadges' => $badges,
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ];
    }

    private function applySearch($query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';

        $query->where(function ($query) use ($term): void {
            $query->where('title', 'like', $term)->orWhere('city', 'like', $term);
        });
    }

    private function perPage(mixed $requested): int
    {
        return max(1, min((int) ($requested ?: 25), self::MAX_PER_PAGE));
    }

    private function canManage(): bool
    {
        return (bool) User::current()?->can('manage directory');
    }

    /** @return array<int, Column> */
    private function columns(): array
    {
        return [
            Column::make('title')->label(__('directory::cp.col_title')),
            Column::make('collection')->label(__('directory::cp.col_type')),
            Column::make('city')->label(__('directory::cp.col_city')),
            Column::make('visibility')->label(__('directory::cp.col_visibility')),
            Column::make('hidden')->label(__('directory::cp.col_hidden')),
            Column::make('team')->label(__('directory::cp.col_team'))->sortable(false),
            Column::make('coordinates')->label(__('directory::cp.col_coordinates'))->sortable(false),
            Column::make('updated_at')->label(__('directory::cp.col_synced')),
        ];
    }

    /** @return array<string, mixed> */
    private function row(DirectoryProfile $profile, bool $canManage): array
    {
        $entry = Entry::find($profile->entry_id)?->in($profile->site);

        return [
            'id' => $profile->id,
            'title' => $profile->title,
            'collection' => $profile->collection,
            'collection_title' => Collections::has($profile->collection) ? DirectoryStats::title($profile->collection) : $profile->collection,
            'city' => $profile->city,
            'visibility' => $profile->visibility === 'public' ? 'public' : 'private',
            'hidden' => (bool) $profile->hidden,
            'team' => $profile->team_id !== null,
            'coordinates' => $profile->lat !== null && $profile->lng !== null,
            'updated_at' => $profile->updated_at?->toIso8601String(),
            'edit_url' => $entry?->editUrl(),
            'can_manage' => $canManage,
            'rebuild_url' => $canManage ? cp_route('directory.profiles.rebuild', ['id' => $profile->id]) : null,
            'unbind_url' => $canManage && $profile->team_id !== null
                ? cp_route('directory.profiles.unbind', ['id' => $profile->id])
                : null,
        ];
    }
}
