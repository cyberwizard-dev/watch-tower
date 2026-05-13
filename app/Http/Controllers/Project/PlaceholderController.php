<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Inertia\Inertia;
use Inertia\Response;

class PlaceholderController extends Controller
{
    public function __invoke(Project $project, string $section): Response
    {
        return Inertia::render('projects/placeholder', [
            'section' => $section,
        ]);
    }
}
