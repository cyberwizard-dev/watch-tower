<?php

namespace App\Watch\Stats;

use App\Models\NotificationSend;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationStats
{
    /**
     * @return array{
     *   totals: array{total:int,total_ms:float,min_ms:float|null,max_ms:float|null,avg_ms:float|null,p95_ms:float|null},
     *   buckets: list<array{bucket:string,count:int,avg_duration:float|null,p95_duration:float|null}>
     * }
     */
    public function summary(Project $project, TimeRange $range, ?string $notificationClass = null): array
    {
        $base = $this->baseQuery($project, $range, $notificationClass);

        $stats = (clone $base)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(duration_ms) AS total_ms')
            ->selectRaw('MIN(duration_ms) AS min_duration')
            ->selectRaw('MAX(duration_ms) AS max_duration')
            ->selectRaw('AVG(duration_ms) AS avg_duration')
            ->first();

        $durations = (clone $base)
            ->whereNotNull('duration_ms')
            ->pluck('duration_ms')
            ->map(fn ($value) => (float) $value)
            ->all();

        return [
            'totals' => [
                'total' => (int) ($stats?->total ?? 0),
                'total_ms' => (float) ($stats?->total_ms ?? 0),
                'min_ms' => $stats?->min_duration !== null ? (float) $stats->min_duration : null,
                'max_ms' => $stats?->max_duration !== null ? (float) $stats->max_duration : null,
                'avg_ms' => $stats?->avg_duration !== null ? (float) $stats->avg_duration : null,
                'p95_ms' => $this->percentile($durations, 0.95),
            ],
            'buckets' => $this->buckets($project, $range, $notificationClass),
        ];
    }

    /**
     * @return LengthAwarePaginator<array-key, array{notification_class:string,hash:string,count:int,avg_ms:float|null,p95_ms:float|null}>
     */
    public function paginatedNotifications(
        Project $project,
        TimeRange $range,
        ?string $search,
        ?string $sort,
        ?string $dir,
        int $page,
        int $perPage,
    ): LengthAwarePaginator {
        return StatsPaginator::paginate(
            items: $this->notifications($project, $range, $search),
            sortable: [
                'notification_class' => 'string',
                'count' => 'numeric',
                'avg_ms' => 'numeric',
                'p95_ms' => 'numeric',
            ],
            sort: $sort ?? 'notification_class',
            dir: $dir ?? 'asc',
            page: $page,
            perPage: $perPage,
        );
    }

    /**
     * @return list<array{notification_class:string,hash:string,count:int,avg_ms:float|null,p95_ms:float|null}>
     */
    public function notifications(Project $project, TimeRange $range, ?string $search = null): array
    {
        $base = $this->baseQuery($project, $range);

        if ($search !== null && $search !== '') {
            $base->where('notification_class', 'like', '%'.$search.'%');
        }

        $rows = (clone $base)
            ->selectRaw('notification_class')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('AVG(duration_ms) AS avg_ms')
            ->groupBy('notification_class')
            ->get();

        $durationsByClass = $this->durationsByClass($project, $range, $search);

        return $rows
            ->map(function ($row) use ($durationsByClass) {
                $class = (string) $row->notification_class;
                $durations = $durationsByClass[$class] ?? [];

                return [
                    'notification_class' => $class,
                    'hash' => sha1($class),
                    'count' => (int) $row->total,
                    'avg_ms' => $row->avg_ms !== null ? (float) $row->avg_ms : null,
                    'p95_ms' => $this->percentile($durations, 0.95),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{
     *   notification_class:string,
     *   hash:string,
     *   totals: array{total:int,total_ms:float,min_ms:float|null,max_ms:float|null,avg_ms:float|null,p95_ms:float|null},
     *   buckets: list<array{bucket:string,count:int,avg_duration:float|null,p95_duration:float|null}>,
     *   sends: list<array<string, mixed>>
     * }|null
     */
    public function notificationDetail(Project $project, TimeRange $range, string $hash): ?array
    {
        $notificationClass = NotificationSend::query()
            ->where('project_id', $project->id)
            ->whereBetween('occurred_at', [$range->from, $range->to])
            ->select('notification_class')
            ->get()
            ->pluck('notification_class')
            ->unique()
            ->first(fn (string $value) => sha1($value) === $hash);

        if ($notificationClass === null) {
            return null;
        }

        $summary = $this->summary($project, $range, $notificationClass);

        $sends = NotificationSend::query()
            ->leftJoin('users', function ($join) {
                $join->on('notification_sends.notifiable_id', '=', 'users.id')
                    ->where('notification_sends.notifiable_type', '=', 'App\\Models\\User');
            })
            ->where('notification_sends.project_id', $project->id)
            ->where('notification_sends.notification_class', $notificationClass)
            ->whereBetween('notification_sends.occurred_at', [$range->from, $range->to])
            ->orderByDesc('notification_sends.occurred_at')
            ->limit(200)
            ->get([
                'notification_sends.id',
                'notification_sends.channel',
                'notification_sends.source_type',
                'notification_sends.source_label',
                'notification_sends.duration_ms',
                'notification_sends.occurred_at',
                'notification_sends.notifiable_type',
                'notification_sends.notifiable_id',
                'notification_sends.queue',
                'notification_sends.status',
                'notification_sends.environment',
                'users.name as user_name',
                'users.email as user_email',
            ])
            ->map(fn (NotificationSend $row) => [
                'id' => $row->id,
                'channel' => $row->channel,
                'source_type' => $row->source_type,
                'source_label' => $row->source_label,
                'duration_ms' => $row->duration_ms,
                'occurred_at' => $row->occurred_at?->toIso8601String(),
                'notifiable_type' => $row->notifiable_type,
                'notifiable_id' => $row->notifiable_id,
                'queue' => $row->queue,
                'status' => $row->status,
                'environment' => $row->environment,
                'user_name' => $row->user_name,
                'user_email' => $row->user_email,
            ])
            ->all();

        return [
            'notification_class' => $notificationClass,
            'hash' => $hash,
            'totals' => $summary['totals'],
            'buckets' => $summary['buckets'],
            'sends' => $sends,
        ];
    }

    /**
     * @return Builder<NotificationSend>
     */
    private function baseQuery(Project $project, TimeRange $range, ?string $notificationClass = null): Builder
    {
        $query = NotificationSend::query()
            ->where('project_id', $project->id)
            ->whereBetween('occurred_at', [$range->from, $range->to]);

        if ($notificationClass !== null) {
            $query->where('notification_class', $notificationClass);
        }

        return $query;
    }

    /**
     * @return list<array{bucket:string,count:int,avg_duration:float|null,p95_duration:float|null}>
     */
    private function buckets(Project $project, TimeRange $range, ?string $notificationClass = null): array
    {
        $bucketCount = 60;
        $totalSeconds = max(1, $range->from->diffInSeconds($range->to));
        $bucketSeconds = max(1, intdiv($totalSeconds, $bucketCount));

        $rows = $this->baseQuery($project, $range, $notificationClass)
            ->orderBy('occurred_at')
            ->get(['duration_ms', 'occurred_at']);

        $start = $range->from->getTimestamp();
        $buckets = [];

        for ($i = 0; $i < $bucketCount; $i++) {
            $buckets[$i] = [
                'bucket' => CarbonImmutable::createFromTimestamp($start + $i * $bucketSeconds)->toIso8601String(),
                'count' => 0,
                'durations' => [],
            ];
        }

        foreach ($rows as $row) {
            $offset = $row->occurred_at->getTimestamp() - $start;
            $idx = max(0, min($bucketCount - 1, intdiv($offset, $bucketSeconds)));
            $buckets[$idx]['count']++;
            if ($row->duration_ms !== null) {
                $buckets[$idx]['durations'][] = (float) $row->duration_ms;
            }
        }

        return array_map(function (array $bucket): array {
            $durations = $bucket['durations'];
            sort($durations);

            return [
                'bucket' => $bucket['bucket'],
                'count' => $bucket['count'],
                'avg_duration' => $durations === [] ? null : array_sum($durations) / count($durations),
                'p95_duration' => $this->percentile($durations, 0.95),
            ];
        }, $buckets);
    }

    /**
     * @return array<string, list<float>>
     */
    private function durationsByClass(Project $project, TimeRange $range, ?string $search): array
    {
        $query = $this->baseQuery($project, $range)->whereNotNull('duration_ms');

        if ($search !== null && $search !== '') {
            $query->where('notification_class', 'like', '%'.$search.'%');
        }

        $buckets = [];
        $query->orderBy('duration_ms')
            ->get(['notification_class', 'duration_ms'])
            ->each(function (NotificationSend $row) use (&$buckets) {
                $buckets[$row->notification_class][] = (float) $row->duration_ms;
            });

        return $buckets;
    }

    /**
     * @param  list<int|float>  $values
     */
    private function percentile(array $values, float $p): ?float
    {
        if ($values === []) {
            return null;
        }
        $sorted = $values;
        sort($sorted);

        $index = (int) floor($p * (count($sorted) - 1));

        return (float) $sorted[$index];
    }
}
