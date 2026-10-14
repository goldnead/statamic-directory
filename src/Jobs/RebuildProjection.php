<?php

namespace Goldnead\Directory\Jobs;

use Goldnead\Directory\Services\ProfileRebuild;
use Goldnead\Directory\Support\RebuildStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RebuildProjection implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public ?string $collection = null,
        public ?string $entryId = null,
        public bool $prune = false,
    ) {}

    public function handle(ProfileRebuild $rebuild): void
    {
        $scope = $this->entryId !== null ? 'entry' : ($this->collection ?? 'all');
        $total = $rebuild->count($this->collection, $this->entryId);

        RebuildStatus::put('running', ['scope' => $scope, 'processed' => 0, 'total' => $total]);

        $result = $rebuild->run(
            $this->collection,
            $this->entryId,
            $this->prune,
            fn (int $processed, int $total) => RebuildStatus::put('running', ['scope' => $scope, 'processed' => $processed, 'total' => $total]),
        );

        RebuildStatus::put('done', [
            'scope' => $scope,
            'processed' => $result['processed'],
            'total' => $total,
            'pruned' => $result['pruned'],
        ]);
    }

    public function failed(Throwable $e): void
    {
        RebuildStatus::put('failed', ['error' => $e->getMessage()]);
    }
}
