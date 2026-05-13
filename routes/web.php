<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Project\CommandsController;
use App\Http\Controllers\Project\ExceptionsController;
use App\Http\Controllers\Project\IssuesController;
use App\Http\Controllers\Project\JobsController;
use App\Http\Controllers\Project\PlaceholderController;
use App\Http\Controllers\Project\RequestsController;
use App\Http\Controllers\Project\ScheduledTasksController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'home'])->name('home');

Route::scopeBindings()
    ->prefix('projects/{project:slug}')
    ->name('projects.')
    ->group(function () {
        Route::redirect('/', '/projects/{project:slug}/dashboard');
        Route::get('/dashboard', [DashboardController::class, 'show'])->name('dashboard');
        Route::get('/requests', [RequestsController::class, 'index'])->name('requests.index');
        Route::get('/exceptions', [ExceptionsController::class, 'index'])->name('exceptions.index');
        Route::get('/issues', [IssuesController::class, 'index'])->name('issues.index');
        Route::get('/issues/{issue}', [IssuesController::class, 'show'])
            ->whereNumber('issue')
            ->name('issues.show');
        Route::patch('/issues/{issue}', [IssuesController::class, 'update'])
            ->whereNumber('issue')
            ->name('issues.update');
        Route::get('/jobs', [JobsController::class, 'index'])->name('jobs.index');
        Route::get('/jobs/{job}', [JobsController::class, 'show'])
            ->where('job', '[A-Za-z0-9._-]+')
            ->name('jobs.show');
        Route::get('/commands', [CommandsController::class, 'index'])->name('commands.index');
        Route::get('/commands/{command}', [CommandsController::class, 'show'])
            ->where('command', '[A-Za-z0-9._:-]+')
            ->name('commands.show');
        Route::get('/scheduled-tasks', [ScheduledTasksController::class, 'index'])->name('scheduled-tasks.index');
        Route::get('/scheduled-tasks/{task}', [ScheduledTasksController::class, 'show'])
            ->where('task', '[A-Fa-f0-9]{40}')
            ->name('scheduled-tasks.show');
        Route::get('/{section}', PlaceholderController::class)
            ->where('section', 'events|queries|notifications|logs|cache|gates|views|models|emails|mail|http-client|dumps|redis')
            ->name('placeholder');
    });
