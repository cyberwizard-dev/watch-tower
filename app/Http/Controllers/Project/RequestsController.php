<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RequestsController extends Controller
{
    public function index(Project $project, Request $request): Response
    {
        $traces = $project->traces()
            ->latest('occurred_at')
            ->when($request->string('status')->toString() === 'error', fn ($q) => $q->where('status_code', '>=', 400))
            ->when($request->string('status')->toString() === 'success', fn ($q) => $q->where('status_code', '<', 400))
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($trace) => [
                'id' => $trace->id,
                'correlation_id' => $trace->correlation_id,
                'method' => $trace->method,
                'uri' => $trace->uri,
                'status_code' => $trace->status_code,
                'duration_ms' => $trace->duration_ms,
                'db_queries_count' => $trace->db_queries_count,
                'has_errors' => $trace->has_errors,
                'environment' => $trace->environment,
                'occurred_at' => $trace->occurred_at?->toIso8601String(),
            ]);

        return Inertia::render('projects/requests/index', [
            'traces' => $traces,
            'filters' => [
                'status' => $request->string('status')->toString() ?: null,
            ],
        ]);
    }
}
