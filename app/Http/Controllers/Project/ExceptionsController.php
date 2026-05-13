<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
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
}
