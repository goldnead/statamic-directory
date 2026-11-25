<?php

namespace Goldnead\Directory\Console;

use Goldnead\Directory\Jobs\RebuildProjection;
use Goldnead\Directory\Services\Collections;
use Goldnead\Directory\Services\ProfileRebuild;
use Goldnead\Directory\Support\RebuildStatus;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class RebuildCommand extends Command
{
    protected $signature = 'directory:rebuild
        {--collection= : Only this profile collection}
        {--entry= : Only this entry id}
        {--prune : Also remove rows whose Entry no longer exists (full rebuild only)}
        {--queue : Dispatch as a queued job instead of running now}';

    protected $description = 'Rebuild the directory projection from the canonical Entries.';

    public function handle(ProfileRebuild $rebuild): int
    {
        $collection = $this->option('collection') ?: null;
        $entry = $this->option('entry') ?: null;

        if ($collection !== null && ! Collections::has($collection)) {
            $this->error("Unknown directory collection [{$collection}].");

            return self::FAILURE;
        }

        if ($this->option('queue')) {
            RebuildStatus::put('queued', ['scope' => $entry !== null ? 'entry' : ($collection ?? 'all')]);

            try {
                RebuildProjection::dispatch($collection, $entry, (bool) $this->option('prune'));
            } catch (Throwable $e) {
                // With the sync driver the job has already recorded its own failure.
                if ((RebuildStatus::get()['state'] ?? null) !== 'failed') {
                    RebuildStatus::put('failed', ['error' => $e->getMessage()]);
                }

                report($e);
                $this->error("Rebuild could not be queued: {$e->getMessage()}");

                return self::FAILURE;
            }

            $this->info('Rebuild queued.');

            return self::SUCCESS;
        }

        try {
            $result = $rebuild->run($collection, $entry, (bool) $this->option('prune'));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Rebuilt {$result['processed']} profile(s); pruned {$result['pruned']}.");

        if ($result['errors'] > 0) {
            $this->error("{$result['errors']} entr(y/ies) could not be projected, see the log.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
