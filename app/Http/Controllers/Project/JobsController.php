<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Watch\Stats\JobStats;
use App\Watch\Stats\TimeRange;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobsController extends Controller
{
    public function index(Project $project, Request $request, JobStats $stats): Response
    {
        $range = TimeRange::fromLabel($request->string('range', '1h')->toString());
        $search = trim($request->string('search')->toString()) ?: null;

        return Inertia::render('projects/jobs/index', [
            'summary' => $stats->summary($project, $range),
            'jobs' => $stats->jobs($project, $range, $search),
            'selectedRange' => $range->label,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function show(Project $project, Request $request, JobStats $stats, string $job): Response
    {
        $range = TimeRange::fromLabel($request->string('range', '30d')->toString());

        $jobClass = $this->decodeJobClass($job);
        $detail = $stats->jobDetail($project, $range, $jobClass);

        abort_if($detail === null, 404);

        return Inertia::render('projects/jobs/show', [
            'detail' => $detail,
            'selectedRange' => $range->label,
        ]);
    }

    private function decodeJobClass(string $segment): string
    {
        // URL slugs use "--" to represent "\\" so the route can avoid encoded backslashes.
        return str_replace('--', '\\', $segment);
    }

    public static function encodeJobClass(string $jobClass): string
    {
        return str_replace('\\', '--', $jobClass);
    }
}
