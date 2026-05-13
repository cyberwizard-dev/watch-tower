<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Watch\Stats\CommandStats;
use App\Watch\Stats\TimeRange;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommandsController extends Controller
{
    public function index(Project $project, Request $request, CommandStats $stats): Response
    {
        $range = TimeRange::fromLabel($request->string('range', '1h')->toString());
        $search = trim($request->string('search')->toString()) ?: null;

        return Inertia::render('projects/commands/index', [
            'summary' => $stats->summary($project, $range),
            'commands' => $stats->commands($project, $range, $search),
            'selectedRange' => $range->label,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function show(Project $project, Request $request, CommandStats $stats, string $command): Response
    {
        $range = TimeRange::fromLabel($request->string('range', '30d')->toString());

        $detail = $stats->commandDetail($project, $range, $command);

        abort_if($detail === null, 404);

        return Inertia::render('projects/commands/show', [
            'detail' => $detail,
            'selectedRange' => $range->label,
        ]);
    }
}
