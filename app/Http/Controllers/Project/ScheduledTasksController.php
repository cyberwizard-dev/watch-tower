<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Watch\Stats\ScheduledTaskStats;
use App\Watch\Stats\TimeRange;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ScheduledTasksController extends Controller
{
    public function index(Project $project, Request $request, ScheduledTaskStats $stats): Response
    {
        $range = TimeRange::fromLabel($request->string('range', '1h')->toString());
        $search = trim($request->string('search')->toString()) ?: null;

        return Inertia::render('projects/scheduled-tasks/index', [
            'summary' => $stats->summary($project, $range),
            'tasks' => $stats->tasks($project, $range, $search),
            'selectedRange' => $range->label,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function show(Project $project, Request $request, ScheduledTaskStats $stats, string $task): Response
    {
        $range = TimeRange::fromLabel($request->string('range', '30d')->toString());

        $detail = $stats->taskDetail($project, $range, $task);

        abort_if($detail === null, 404);

        return Inertia::render('projects/scheduled-tasks/show', [
            'detail' => $detail,
            'selectedRange' => $range->label,
        ]);
    }
}
