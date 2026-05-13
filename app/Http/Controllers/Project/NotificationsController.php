<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Watch\Stats\NotificationStats;
use App\Watch\Stats\TimeRange;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationsController extends Controller
{
    public function index(Project $project, Request $request, NotificationStats $stats): Response
    {
        $range = TimeRange::fromLabel($request->string('range', '24h')->toString());
        $search = trim($request->string('search')->toString()) ?: null;
        $sort = $request->string('sort')->toString() ?: null;
        $dir = $request->string('dir')->toString() ?: null;
        $page = max(1, (int) $request->integer('page', 1));

        return Inertia::render('projects/notifications/index', [
            'summary' => $stats->summary($project, $range),
            'notifications' => $stats->paginatedNotifications($project, $range, $search, $sort, $dir, $page, 25),
            'selectedRange' => $range->label,
            'filters' => [
                'search' => $search,
                'sort' => $sort ?? 'notification_class',
                'dir' => $dir ?? 'asc',
            ],
        ]);
    }

    public function show(Project $project, Request $request, NotificationStats $stats, string $notification): Response
    {
        $range = TimeRange::fromLabel($request->string('range', '7d')->toString());

        $detail = $stats->notificationDetail($project, $range, $notification);

        abort_if($detail === null, 404);

        return Inertia::render('projects/notifications/show', [
            'detail' => $detail,
            'selectedRange' => $range->label,
        ]);
    }
}
