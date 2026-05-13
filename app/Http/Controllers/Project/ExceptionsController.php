<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\ErrorGroup;
use App\Models\ErrorOccurrence;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ExceptionsController extends Controller
{
    public function index(Project $project, Request $request): Response
    {
        $status = $request->string('status')->toString() ?: 'unresolved';

        $groups = $project->errorGroups()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('last_occurrence_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($group) => [
                'id' => $group->id,
                'fingerprint' => $group->fingerprint,
                'exception_class' => $group->exception_class,
                'first_message' => $group->first_message,
                'first_file' => $group->first_file,
                'first_line' => $group->first_line,
                'total_count' => $group->total_count,
                'first_occurrence_at' => $group->first_occurrence_at?->toIso8601String(),
                'last_occurrence_at' => $group->last_occurrence_at?->toIso8601String(),
                'status' => $group->status,
            ]);

        return Inertia::render('projects/exceptions/index', [
            'groups' => $groups,
            'filters' => ['status' => $status],
        ]);
    }

    public function show(Project $project, string $exception): Response
    {
        /** @var ErrorGroup $group */
        $group = $project->errorGroups()
            ->where('id', $exception)
            ->firstOrFail();

        /** @var ErrorOccurrence|null $latest */
        $latest = $group->occurrences()
            ->latest('occurred_at')
            ->first();

        $usersCount = (int) $group->occurrences()
            ->whereNotNull('user_identifier')
            ->distinct('user_identifier')
            ->count('user_identifier');

        $environmentCounts = $group->occurrences()
            ->select('environment', DB::raw('count(*) as total'))
            ->groupBy('environment')
            ->get()
            ->map(fn ($row) => [
                'environment' => $row->environment ?? 'unknown',
                'count' => (int) $row->total,
            ])
            ->all();

        $sparkline = $this->sparkline($project, $group->id);

        return Inertia::render('projects/exceptions/show', [
            'exception' => [
                'id' => $group->id,
                'exception_class' => $group->exception_class,
                'short_class' => $this->shortClass($group->exception_class),
                'first_message' => $group->first_message,
                'first_file' => $group->first_file,
                'first_line' => $group->first_line,
                'total_count' => $group->total_count,
                'users_count' => $usersCount,
                'first_occurrence_at' => $group->first_occurrence_at?->toIso8601String(),
                'last_occurrence_at' => $group->last_occurrence_at?->toIso8601String(),
                'status' => $group->status,
                'is_handled' => (bool) $group->is_handled,
                'framework_version' => $group->framework_version,
                'language_version' => $group->language_version,
                'environments' => $environmentCounts,
                'sparkline' => $sparkline,
                'latest_occurrence' => $latest ? [
                    'id' => $latest->id,
                    'message' => $latest->message,
                    'file' => $latest->file,
                    'line' => $latest->line,
                    'stacktrace' => $latest->stacktrace ?? [],
                    'context' => $latest->context ?? [],
                    'occurred_at' => $latest->occurred_at?->toIso8601String(),
                ] : null,
            ],
        ]);
    }

    /**
     * @return list<int>
     */
    private function sparkline(Project $project, string $groupId): array
    {
        $start = CarbonImmutable::now()->subDays(14)->startOfDay();
        $end = CarbonImmutable::now()->endOfDay();

        $rows = ErrorOccurrence::query()
            ->where('project_id', $project->id)
            ->where('error_group_id', $groupId)
            ->whereBetween('occurred_at', [$start, $end])
            ->selectRaw('strftime("%Y-%m-%d", occurred_at) as day, count(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->all();

        $buckets = [];
        for ($i = 0; $i < 14; $i++) {
            $day = $start->addDays($i)->format('Y-m-d');
            $buckets[] = (int) ($rows[$day] ?? 0);
        }

        return $buckets;
    }

    private function shortClass(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return $parts[count($parts) - 1] ?? $fqcn;
    }
}
