<?php

namespace App\Jobs;

use App\Models\Project;
use App\Watch\EventStore;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessWatchEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 5;

    /**
     * @param  array{type: string, id?: string, data: array<string, mixed>}  $event
     */
    public function __construct(
        public readonly string $projectId,
        public readonly array $event,
    ) {
        // $this->onQueue('watch-events');
    }

    public function handle(EventStore $store): void
    {
        $project = Project::find($this->projectId);

        if (! $project) {
            return;
        }

        $store->store($project, $this->event);
    }
}
