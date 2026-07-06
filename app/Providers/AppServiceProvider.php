<?php

namespace App\Providers;

use App\Models\ErrorOccurrence;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerGates();
        $this->registerRouteBindings();
        $this->registerExceptionAlerts();
    }

    protected function registerRouteBindings(): void
    {
        Route::model('admin', User::class);
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function registerGates(): void
    {
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        Gate::define('manage-admins', fn (User $user) => $user->isSuperAdmin());

        Gate::define('manage-projects', fn (User $user) => $user->isSuperAdmin());

        Gate::define('view-project', fn (User $user, Project $project) => $user->isSuperAdmin()
            || $project->admins()->whereKey($user->getKey())->exists());
    }

    protected function registerExceptionAlerts(): void
    {
        ErrorOccurrence::created(function (ErrorOccurrence $occurrence) {
            try {
                $project = $occurrence->project;
                if (! $project) {
                    return;
                }

                // Prevent duplicate / spam notifications for the same error group within 30 minutes
                $cacheKey = 'error_group_alert_sent:'.$occurrence->error_group_id;
                $isNewGroup = $occurrence->errorGroup && $occurrence->errorGroup->total_count === 1;
                $shouldAlert = $isNewGroup || ! Cache::has($cacheKey);

                if (! $shouldAlert) {
                    return;
                }

                Cache::put($cacheKey, true, now()->addMinutes(30));

                // Get target recipients for exception alerts
                $recipients = [];
                if ($envRecipient = env('MAIL_ALERT_RECIPIENT')) {
                    $recipients = array_filter(array_map('trim', explode(',', $envRecipient)));
                }

                if (empty($recipients)) {
                    $recipients = $project->admins->pluck('email')->all();
                }

                if (empty($recipients)) {
                    $recipients = User::pluck('email')->all();
                }

                if (empty($recipients)) {
                    // Failover fallback
                    $recipients = ['eminibest@gmail.com'];
                }

                $subject = '🚨 [WatchTower] New Exception in '.$project->name.' ('.$occurrence->environment.')';

                $body = "
                <div style='font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                    <h2 style='color: #dc2626; margin-top: 0;'>New Exception Detected</h2>
                    <p><strong>Project:</strong> {$project->name}</p>
                    <p><strong>Environment:</strong> ".e($occurrence->environment).'</p>
                    <p><strong>Exception Class:</strong> <code>'.e($occurrence->exception_class)."</code></p>
                    <p><strong>Message:</strong> <span style='color: #4a5568;'>".e($occurrence->message).'</span></p>
                    <p><strong>File:</strong> <code>'.e($occurrence->file).':'.e($occurrence->line).'</code></p>
                    <p><strong>Occurred At:</strong> '.e($occurrence->occurred_at)."</p>
                    <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
                    <a href='".config('app.url')."/projects/{$project->id}/exceptions/{$occurrence->error_group_id}' 
                       style='display: inline-block; background-color: #3b82f6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>
                       View Exception in Dashboard
                    </a>
                </div>
                ";

                Mail::html($body, function ($message) use ($recipients, $subject) {
                    $message->to($recipients)
                        ->subject($subject);
                });
            } catch (\Throwable $e) {
                Log::error('Failed to send exception email alert: '.$e->getMessage());
            }
        });
    }
}
