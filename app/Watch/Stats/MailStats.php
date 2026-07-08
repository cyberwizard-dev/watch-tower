<?php

namespace App\Watch\Stats;

use App\Models\MailSend;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class MailStats
{
    /**
     * @return array{
     *   totals: array{total:int,total_ms:float,min_ms:float|null,max_ms:float|null,avg_ms:float|null,p95_ms:float|null},
     *   buckets: list<array{bucket:string,count:int,avg_duration:float|null,p95_duration:float|null}>
     * }
     */
    public function summary(Project $project, TimeRange $range, ?string $mailableClass = null): array
    {
        $base = $this->baseQuery($project, $range, $mailableClass);

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
            'buckets' => $this->buckets($project, $range, $mailableClass),
        ];
    }

    /**
     * @return LengthAwarePaginator<array-key, array{mailable_class:string,hash:string,count:int,avg_ms:float|null,p95_ms:float|null}>
     */
    public function paginatedMails(
        Project $project,
        TimeRange $range,
        ?string $search,
        ?string $sort,
        ?string $dir,
        int $page,
        int $perPage,
    ): LengthAwarePaginator {
        return StatsPaginator::paginate(
            items: $this->mails($project, $range, $search),
            sortable: [
                'mailable_class' => 'string',
                'count' => 'numeric',
                'avg_ms' => 'numeric',
                'p95_ms' => 'numeric',
            ],
            sort: $sort ?? 'mailable_class',
            dir: $dir ?? 'asc',
            page: $page,
            perPage: $perPage,
        );
    }

    /**
     * @return list<array{mailable_class:string,hash:string,count:int,avg_ms:float|null,p95_ms:float|null}>
     */
    public function mails(Project $project, TimeRange $range, ?string $search = null): array
    {
        $base = $this->baseQuery($project, $range);

        if ($search !== null && $search !== '') {
            $base->where('mailable_class', 'like', '%'.$search.'%');
        }

        $rows = (clone $base)
            ->selectRaw('mailable_class')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('AVG(duration_ms) AS avg_ms')
            ->groupBy('mailable_class')
            ->get();

        $durationsByClass = $this->durationsByClass($project, $range, $search);

        return $rows
            ->map(function ($row) use ($durationsByClass) {
                $class = (string) $row->mailable_class;
                $durations = $durationsByClass[$class] ?? [];

                return [
                    'mailable_class' => $class,
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
     *   mailable_class:string,
     *   hash:string,
     *   totals: array{total:int,total_ms:float,min_ms:float|null,max_ms:float|null,avg_ms:float|null,p95_ms:float|null},
     *   buckets: list<array{bucket:string,count:int,avg_duration:float|null,p95_duration:float|null}>,
     *   sends: list<array<string, mixed>>
     * }|null
     */
    public function mailDetail(Project $project, TimeRange $range, string $hash): ?array
    {
        $mailableClass = MailSend::query()
            ->where('project_id', $project->id)
            ->whereBetween('occurred_at', [$range->from, $range->to])
            ->select('mailable_class')
            ->get()
            ->pluck('mailable_class')
            ->unique()
            ->first(fn (string $value) => sha1($value) === $hash);

        if ($mailableClass === null) {
            return null;
        }

        $summary = $this->summary($project, $range, $mailableClass);

        $sends = MailSend::query()
            ->where('project_id', $project->id)
            ->where('mailable_class', $mailableClass)
            ->whereBetween('occurred_at', [$range->from, $range->to])
            ->orderByDesc('occurred_at')
            ->limit(200)
            ->get([
                'id',
                'subject',
                'mailer',
                'recipients_count',
                'attachments_count',
                'duration_ms',
                'source_type',
                'source_label',
                'occurred_at',
                'from_address',
                'from_name',
                'recipients_to',
                'recipients_cc',
                'recipients_bcc',
                'queue',
                'status',
                'environment',
            ])
            ->map(fn (MailSend $row) => [
                'id' => $row->id,
                'subject' => $row->subject,
                'mailer' => $row->mailer,
                'recipients_count' => (int) $row->recipients_count,
                'attachments_count' => (int) $row->attachments_count,
                'duration_ms' => $row->duration_ms,
                'source_type' => $row->source_type,
                'source_label' => $row->source_label,
                'occurred_at' => $row->occurred_at?->toIso8601String(),
                'from_address' => $row->from_address,
                'from_name' => $row->from_name,
                'recipients_to' => $row->recipients_to,
                'recipients_cc' => $row->recipients_cc,
                'recipients_bcc' => $row->recipients_bcc,
                'queue' => $row->queue,
                'status' => $row->status,
                'environment' => $row->environment,
            ])
            ->all();

        return [
            'mailable_class' => $mailableClass,
            'hash' => $hash,
            'totals' => $summary['totals'],
            'buckets' => $summary['buckets'],
            'sends' => $sends,
        ];
    }

    /**
     * @return Builder<MailSend>
     */
    private function baseQuery(Project $project, TimeRange $range, ?string $mailableClass = null): Builder
    {
        $query = MailSend::query()
            ->where('project_id', $project->id)
            ->whereBetween('occurred_at', [$range->from, $range->to]);

        if ($mailableClass !== null) {
            $query->where('mailable_class', $mailableClass);
        }

        return $query;
    }

    /**
     * @return list<array{bucket:string,count:int,avg_duration:float|null,p95_duration:float|null}>
     */
    private function buckets(Project $project, TimeRange $range, ?string $mailableClass = null): array
    {
        $bucketCount = 60;
        $totalSeconds = max(1, $range->from->diffInSeconds($range->to));
        $bucketSeconds = max(1, intdiv($totalSeconds, $bucketCount));

        $rows = $this->baseQuery($project, $range, $mailableClass)
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
            $query->where('mailable_class', 'like', '%'.$search.'%');
        }

        $buckets = [];
        $query->orderBy('duration_ms')
            ->get(['mailable_class', 'duration_ms'])
            ->each(function (MailSend $row) use (&$buckets) {
                $buckets[$row->mailable_class][] = (float) $row->duration_ms;
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
