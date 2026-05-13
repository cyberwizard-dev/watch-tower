<?php

namespace Database\Seeders;

use App\Models\CommandRun;
use App\Models\ErrorGroup;
use App\Models\ErrorOccurrence;
use App\Models\MailSend;
use App\Models\NotificationSend;
use App\Models\Organization;
use App\Models\Project;
use App\Models\QueueJobRun;
use App\Models\ScheduledTaskRun;
use App\Models\Trace;
use App\Models\TraceQuery;
use App\Models\User;
use App\Watch\Fingerprinter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()->firstOrCreate(
            ['slug' => 'acme'],
            ['name' => 'Acme Inc.', 'plan' => 'pro', 'retention_days' => 30]
        );

        $teamMembers = [
            ['name' => 'Jordan Pierce', 'email' => 'jordan@acme.test'],
            ['name' => 'Riley Chen', 'email' => 'riley@acme.test'],
            ['name' => 'Sam Okafor', 'email' => 'sam@acme.test'],
            ['name' => 'Avery Patel', 'email' => 'avery@acme.test'],
        ];

        $teamUsers = collect($teamMembers)->map(fn (array $member) => User::query()->firstOrCreate(
            ['email' => $member['email']],
            [
                'name' => $member['name'],
                'password' => Hash::make('password'),
                'organization_id' => $organization->id,
                'role' => 'member',
            ]
        ))->all();

        $project = Project::query()->firstOrCreate(
            ['slug' => 'acme-app'],
            [
                'organization_id' => $organization->id,
                'name' => 'Acme App',
                'description' => 'Production demo project',
                'api_key' => 'pk_demo_'.Str::random(40),
                'api_secret_hash' => Hash::make('sk_demo_'.Str::random(40)),
                'sampling_rate' => 1.0,
                'retention_days' => 30,
            ]
        );

        $this->command?->info("Seeding traces for project: {$project->name}");

        $now = CarbonImmutable::now();
        $start = $now->subDay();

        $endUsers = [];
        for ($i = 1; $i <= 60; $i++) {
            $endUsers[] = [
                'id' => 'usr_'.Str::random(10),
                'email' => 'end-user-'.$i.'@flywp.test',
            ];
        }

        $routes = [
            ['GET', '/api/users', 200, 12],
            ['GET', '/api/users', 200, 18],
            ['GET', '/api/users/{id}', 200, 9],
            ['POST', '/api/users', 201, 45],
            ['POST', '/api/orders', 201, 132],
            ['GET', '/api/orders', 200, 27],
            ['GET', '/api/orders/{id}', 200, 16],
            ['PUT', '/api/orders/{id}', 200, 64],
            ['DELETE', '/api/orders/{id}', 204, 30],
            ['GET', '/dashboard', 200, 88],
            ['GET', '/settings', 200, 44],
            ['POST', '/api/payments', 200, 412],
            ['POST', '/api/payments', 500, 1240],
            ['GET', '/api/reports/heavy', 200, 1850],
            ['GET', '/api/search', 200, 230],
            ['GET', '/api/search', 404, 21],
            ['GET', '/api/products', 200, 22],
            ['POST', '/api/checkout', 422, 18],
            ['POST', '/api/checkout', 200, 320],
            ['GET', '/api/notifications', 200, 14],
        ];

        $totalTraces = 480;
        $traces = [];

        for ($i = 0; $i < $totalTraces; $i++) {
            $route = $routes[array_rand($routes)];
            [$method, $uri, $baseStatus, $baseDuration] = $route;

            $jitter = random_int(-30, 200) / 100;
            $duration = max(2, (int) round($baseDuration * (1 + $jitter)));

            $status = $baseStatus;
            if (random_int(1, 100) <= 4) {
                $status = 500;
            } elseif (random_int(1, 100) <= 8 && $baseStatus < 400) {
                $status = [400, 401, 403, 404, 422][array_rand([400, 401, 403, 404, 422])];
            }

            $offsetSeconds = random_int(0, 86_400);
            $occurredAt = $start->addSeconds($offsetSeconds);

            $hasUser = random_int(1, 100) <= 80;
            $endUser = $hasUser ? $endUsers[array_rand($endUsers)] : null;

            $traces[] = [
                'id' => (string) Str::uuid(),
                'project_id' => $project->id,
                'correlation_id' => (string) Str::uuid(),
                'method' => $method,
                'uri' => $uri,
                'status_code' => $status,
                'user_identifier' => $endUser['id'] ?? null,
                'user_email' => $endUser['email'] ?? null,
                'duration_ms' => $duration,
                'db_queries_count' => random_int(0, 18),
                'db_time_ms' => random_int(0, max(1, (int) ($duration / 3))),
                'memory_used_kb' => random_int(2_048, 32_768),
                'memory_peak_kb' => random_int(4_096, 65_536),
                'environment' => 'production',
                'release_version' => 'v1.4.2',
                'hostname' => 'web-'.random_int(1, 3),
                'ip_address' => '203.0.113.'.random_int(1, 254),
                'user_agent' => 'Mozilla/5.0 (compatible; Demo/1.0)',
                'headers' => json_encode(['accept' => 'application/json']),
                'request_data' => json_encode([]),
                'response_data' => json_encode(['status' => 'ok']),
                'has_errors' => $status >= 500,
                'has_slow_queries' => $duration > 800,
                'occurred_at' => $occurredAt,
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ];
        }

        foreach (array_chunk($traces, 200) as $chunk) {
            Trace::query()->insert($chunk);
        }

        $this->seedExceptions($project, $traces, $start, $teamUsers);
        $this->seedQueries($project, $traces);
        $this->seedJobs($project, $start);
        $this->seedCommands($project, $start);
        $this->seedScheduledTasks($project, $start, $now);
        $this->seedMail($project, $start);
        $this->seedNotifications($project, $start);
    }

    /**
     * @param  list<array<string, mixed>>  $traces
     * @param  list<User>  $teamUsers
     */
    private function seedExceptions(Project $project, array $traces, CarbonImmutable $start, array $teamUsers): void
    {
        $templates = [
            [
                'class' => 'Illuminate\\Database\\QueryException',
                'message' => 'SQLSTATE[42S22]: Column not found: orders.status',
                'file' => 'app/Repositories/OrderRepository.php',
                'line' => 84,
                'is_handled' => false,
                'stacktrace' => [
                    ['file' => 'app/Repositories/OrderRepository.php', 'line' => 84, 'function' => 'findByStatus', 'class' => 'App\\Repositories\\OrderRepository'],
                    ['file' => 'app/Http/Controllers/OrderController.php', 'line' => 47, 'function' => 'index', 'class' => 'App\\Http\\Controllers\\OrderController'],
                    ['file' => 'vendor/laravel/framework/src/Illuminate/Routing/Controller.php', 'line' => 54, 'function' => 'callAction', 'class' => 'Illuminate\\Routing\\Controller'],
                    ['file' => 'vendor/laravel/framework/src/Illuminate/Routing/ControllerDispatcher.php', 'line' => 43, 'function' => 'dispatch', 'class' => 'Illuminate\\Routing\\ControllerDispatcher'],
                    ['file' => 'vendor/laravel/framework/src/Illuminate/Routing/Route.php', 'line' => 260, 'function' => 'runController', 'class' => 'Illuminate\\Routing\\Route'],
                ],
            ],
            [
                'class' => 'GuzzleHttp\\Exception\\ConnectException',
                'message' => 'cURL error 28: Connection timed out after 30000 ms',
                'file' => 'app/Services/PaymentGateway.php',
                'line' => 122,
                'is_handled' => true,
                'stacktrace' => [
                    ['file' => 'app/Services/PaymentGateway.php', 'line' => 122, 'function' => 'charge', 'class' => 'App\\Services\\PaymentGateway'],
                    ['file' => 'app/Http/Controllers/PaymentController.php', 'line' => 73, 'function' => 'store', 'class' => 'App\\Http\\Controllers\\PaymentController'],
                    ['file' => 'vendor/guzzlehttp/guzzle/src/Handler/CurlFactory.php', 'line' => 211, 'function' => 'createRejection', 'class' => 'GuzzleHttp\\Handler\\CurlFactory'],
                ],
            ],
            [
                'class' => 'TypeError',
                'message' => 'Argument #1 ($id) must be of type int, string given',
                'file' => 'app/Http/Controllers/UserController.php',
                'line' => 56,
                'is_handled' => false,
                'stacktrace' => [
                    ['file' => 'app/Http/Controllers/UserController.php', 'line' => 56, 'function' => 'show', 'class' => 'App\\Http\\Controllers\\UserController'],
                    ['file' => 'app/Services/UserResolver.php', 'line' => 21, 'function' => 'resolve', 'class' => 'App\\Services\\UserResolver'],
                    ['file' => 'vendor/laravel/framework/src/Illuminate/Routing/Route.php', 'line' => 260, 'function' => 'runController', 'class' => 'Illuminate\\Routing\\Route'],
                ],
            ],
            [
                'class' => 'Symfony\\Component\\HttpKernel\\Exception\\NotFoundHttpException',
                'message' => 'The route api/legacy could not be found.',
                'file' => 'vendor/symfony/http-kernel/HttpKernel.php',
                'line' => 138,
                'is_handled' => true,
                'stacktrace' => [
                    ['file' => 'vendor/symfony/http-kernel/HttpKernel.php', 'line' => 138, 'function' => 'handleRaw', 'class' => 'Symfony\\Component\\HttpKernel\\HttpKernel'],
                    ['file' => 'vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php', 'line' => 169, 'function' => 'handle', 'class' => 'Illuminate\\Foundation\\Http\\Kernel'],
                ],
            ],
            [
                'class' => 'RuntimeException',
                'message' => 'Failed to dispatch InvoiceMailer: queue connection unavailable',
                'file' => 'app/Jobs/SendInvoice.php',
                'line' => 41,
                'is_handled' => false,
                'stacktrace' => [
                    ['file' => 'app/Jobs/SendInvoice.php', 'line' => 41, 'function' => 'handle', 'class' => 'App\\Jobs\\SendInvoice'],
                    ['file' => 'vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php', 'line' => 122, 'function' => 'call', 'class' => 'Illuminate\\Queue\\CallQueuedHandler'],
                    ['file' => 'vendor/laravel/framework/src/Illuminate/Queue/Jobs/Job.php', 'line' => 99, 'function' => 'fire', 'class' => 'Illuminate\\Queue\\Jobs\\Job'],
                ],
            ],
            [
                'class' => 'Illuminate\\Auth\\AuthenticationException',
                'message' => 'Unauthenticated.',
                'file' => 'app/Http/Middleware/Authenticate.php',
                'line' => 27,
                'is_handled' => true,
                'stacktrace' => [
                    ['file' => 'app/Http/Middleware/Authenticate.php', 'line' => 27, 'function' => 'redirectTo', 'class' => 'App\\Http\\Middleware\\Authenticate'],
                    ['file' => 'vendor/laravel/framework/src/Illuminate/Auth/Middleware/Authenticate.php', 'line' => 81, 'function' => 'unauthenticated', 'class' => 'Illuminate\\Auth\\Middleware\\Authenticate'],
                ],
            ],
            [
                'class' => 'Illuminate\\Validation\\ValidationException',
                'message' => 'The given data was invalid.',
                'file' => 'app/Http/Requests/CheckoutRequest.php',
                'line' => 38,
                'is_handled' => true,
                'stacktrace' => [
                    ['file' => 'app/Http/Requests/CheckoutRequest.php', 'line' => 38, 'function' => 'rules', 'class' => 'App\\Http\\Requests\\CheckoutRequest'],
                    ['file' => 'vendor/laravel/framework/src/Illuminate/Foundation/Http/FormRequest.php', 'line' => 145, 'function' => 'failedValidation', 'class' => 'Illuminate\\Foundation\\Http\\FormRequest'],
                ],
            ],
            [
                'class' => 'Redis\\RedisException',
                'message' => 'Connection refused: tcp://redis:6379',
                'file' => 'app/Services/CacheWarmer.php',
                'line' => 64,
                'is_handled' => false,
                'stacktrace' => [
                    ['file' => 'app/Services/CacheWarmer.php', 'line' => 64, 'function' => 'warm', 'class' => 'App\\Services\\CacheWarmer'],
                    ['file' => 'app/Console/Commands/WarmCache.php', 'line' => 22, 'function' => 'handle', 'class' => 'App\\Console\\Commands\\WarmCache'],
                ],
            ],
        ];

        $priorities = ['none', 'none', 'none', 'low', 'low', 'medium', 'medium', 'high'];
        $descriptions = [
            null,
            null,
            "Started appearing after the **v1.4.2** deploy.\n\nLikely related to the new orders table migration that added the `status` column.",
            "Customer reports indicate this happens at checkout when payment provider is slow.\n\n- Affecting ~3% of payments\n- Retry queue picks up most failures",
            "Reproduced locally by passing a numeric string to the `users.show` route.\n\nNeed to add explicit casting at the controller boundary.",
        ];

        $errorTraces = array_values(array_filter($traces, fn (array $t): bool => $t['has_errors']));
        $errorRows = [];
        $groups = [];
        $fingerprinter = new Fingerprinter;
        $environments = ['production', 'production', 'production', 'staging'];

        $errorCount = max(120, count($errorTraces) * 2);

        for ($i = 0; $i < $errorCount; $i++) {
            $tpl = $templates[array_rand($templates)];
            $trace = $errorTraces !== [] && random_int(1, 100) <= 70
                ? $errorTraces[array_rand($errorTraces)]
                : null;

            $occurredAt = $trace
                ? CarbonImmutable::parse($trace['occurred_at'])
                : $start->addSeconds(random_int(0, 86_400));

            $fingerprint = $fingerprinter->forException([
                'class' => $tpl['class'],
                'file' => $tpl['file'],
                'line' => $tpl['line'],
            ]);

            if (! isset($groups[$fingerprint])) {
                $groups[$fingerprint] = [
                    'id' => (string) Str::uuid(),
                    'project_id' => $project->id,
                    'fingerprint' => $fingerprint,
                    'display_number' => count($groups) + 1,
                    'exception_class' => $tpl['class'],
                    'first_message' => $tpl['message'],
                    'first_file' => $tpl['file'],
                    'first_line' => $tpl['line'],
                    'total_count' => 0,
                    'first_occurrence_at' => $occurredAt,
                    'last_occurrence_at' => $occurredAt,
                    'status' => 'unresolved',
                    'priority' => $priorities[array_rand($priorities)],
                    'description' => $descriptions[array_rand($descriptions)],
                    'is_handled' => $tpl['is_handled'],
                    'linear_issue_url' => null,
                    'subscriber_ids' => json_encode([]),
                    'framework_version' => '12.35.1',
                    'language_version' => '8.4.0',
                    'assigned_to_user_id' => random_int(1, 100) <= 60 ? $teamUsers[array_rand($teamUsers)]->id : null,
                    'tags' => json_encode([]),
                    'created_at' => $occurredAt,
                    'updated_at' => $occurredAt,
                ];
            }

            $groups[$fingerprint]['total_count']++;
            if ($occurredAt->lt($groups[$fingerprint]['first_occurrence_at'])) {
                $groups[$fingerprint]['first_occurrence_at'] = $occurredAt;
            }
            if ($occurredAt->gt($groups[$fingerprint]['last_occurrence_at'])) {
                $groups[$fingerprint]['last_occurrence_at'] = $occurredAt;
                $groups[$fingerprint]['updated_at'] = $occurredAt;
            }

            $errorRows[] = [
                'id' => (string) Str::uuid(),
                'project_id' => $project->id,
                'trace_id' => $trace['id'] ?? null,
                'error_group_id' => $groups[$fingerprint]['id'],
                'exception_class' => $tpl['class'],
                'message' => $tpl['message'],
                'stacktrace' => json_encode($tpl['stacktrace']),
                'fingerprint' => $fingerprint,
                'user_identifier' => random_int(1, 100) <= 70 ? 'user_'.random_int(1, 40) : null,
                'user_email' => random_int(1, 100) <= 40 ? 'user'.random_int(1, 40).'@example.test' : null,
                'file' => $tpl['file'],
                'line' => $tpl['line'],
                'is_handled' => $tpl['is_handled'],
                'environment' => $environments[array_rand($environments)],
                'release_version' => 'v1.4.2',
                'context' => json_encode([
                    'request' => ['method' => 'GET', 'url' => '/api/example'],
                ]),
                'occurred_at' => $occurredAt,
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ];
        }

        ErrorGroup::query()->insert(array_values($groups));
        foreach (array_chunk($errorRows, 200) as $chunk) {
            ErrorOccurrence::query()->insert($chunk);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $traces
     */
    private function seedQueries(Project $project, array $traces): void
    {
        /** @var list<array{sql:string,type:string,connection:string,weight:int,base_ms:float,jitter_ms:float}> $templates */
        $templates = [
            ['sql' => 'select * from `users` where `id` = ? limit 1', 'type' => 'select', 'connection' => 'mysql', 'weight' => 220, 'base_ms' => 0.6, 'jitter_ms' => 0.6],
            ['sql' => 'select * from `users` where `email` = ? limit 1', 'type' => 'select', 'connection' => 'mysql', 'weight' => 80, 'base_ms' => 0.8, 'jitter_ms' => 0.6],
            ['sql' => 'select `id`, `name`, `email` from `users` where `organization_id` = ? order by `name` asc', 'type' => 'select', 'connection' => 'mysql', 'weight' => 30, 'base_ms' => 1.4, 'jitter_ms' => 0.9],
            ['sql' => 'select count(*) as aggregate from `users` where `organization_id` = ? and `deleted_at` is null', 'type' => 'select', 'connection' => 'mysql', 'weight' => 25, 'base_ms' => 1.1, 'jitter_ms' => 0.7],
            ['sql' => 'update `users` set `last_seen_at` = ?, `updated_at` = ? where `id` = ?', 'type' => 'update', 'connection' => 'mysql', 'weight' => 60, 'base_ms' => 1.5, 'jitter_ms' => 1.2],
            ['sql' => 'select * from `organizations` where `slug` = ? limit 1', 'type' => 'select', 'connection' => 'mysql', 'weight' => 95, 'base_ms' => 0.7, 'jitter_ms' => 0.5],
            ['sql' => 'select * from `projects` where `organization_id` = ? order by `created_at` desc', 'type' => 'select', 'connection' => 'mysql', 'weight' => 55, 'base_ms' => 1.3, 'jitter_ms' => 0.8],
            ['sql' => 'select `projects`.*, (select count(*) from `error_groups` where `projects`.`id` = `error_groups`.`project_id` and `error_groups`.`status` = ?) as `open_issues` from `projects` where `slug` = ? limit 1', 'type' => 'select', 'connection' => 'mysql', 'weight' => 70, 'base_ms' => 2.4, 'jitter_ms' => 2.6],
            ['sql' => 'select * from `error_groups` where `project_id` = ? and `status` = ? order by `last_occurrence_at` desc limit 25 offset ?', 'type' => 'select', 'connection' => 'mysql', 'weight' => 50, 'base_ms' => 2.1, 'jitter_ms' => 1.8],
            ['sql' => 'select count(*) as aggregate from `error_groups` where `project_id` = ? and `status` = ?', 'type' => 'select', 'connection' => 'mysql', 'weight' => 35, 'base_ms' => 1.2, 'jitter_ms' => 0.8],
            ['sql' => 'select * from `error_occurrences` where `error_group_id` = ? order by `occurred_at` desc limit 1', 'type' => 'select', 'connection' => 'mysql', 'weight' => 65, 'base_ms' => 1.8, 'jitter_ms' => 1.5],
            ['sql' => 'select `environment`, count(*) as `total` from `error_occurrences` where `error_group_id` = ? group by `environment`', 'type' => 'select', 'connection' => 'mysql', 'weight' => 25, 'base_ms' => 2.6, 'jitter_ms' => 2.4],
            ['sql' => 'insert into `error_occurrences` (`error_group_id`, `project_id`, `message`, `file`, `line`, `stacktrace`, `context`, `environment`, `occurred_at`, `created_at`, `updated_at`) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', 'type' => 'insert', 'connection' => 'mysql', 'weight' => 30, 'base_ms' => 3.2, 'jitter_ms' => 2.5],
            ['sql' => 'update `error_groups` set `total_count` = `total_count` + 1, `last_occurrence_at` = ?, `updated_at` = ? where `id` = ?', 'type' => 'update', 'connection' => 'mysql', 'weight' => 35, 'base_ms' => 2.1, 'jitter_ms' => 1.6],
            ['sql' => 'select * from `traces` where `project_id` = ? and `occurred_at` between ? and ? order by `occurred_at` desc limit ?', 'type' => 'select', 'connection' => 'mysql', 'weight' => 40, 'base_ms' => 4.6, 'jitter_ms' => 6.5],
            ['sql' => 'select `bucket`, count(*) as `total` from `traces` where `project_id` = ? and `occurred_at` between ? and ? group by `bucket` order by `bucket` asc', 'type' => 'select', 'connection' => 'mysql', 'weight' => 18, 'base_ms' => 7.3, 'jitter_ms' => 9.8],
            ['sql' => 'select avg(`duration_ms`) as `avg`, max(`duration_ms`) as `max` from `traces` where `project_id` = ? and `occurred_at` between ? and ?', 'type' => 'select', 'connection' => 'mysql', 'weight' => 15, 'base_ms' => 6.2, 'jitter_ms' => 8.1],
            ['sql' => 'select * from `queue_job_runs` where `project_id` = ? and `status` = ? order by `dispatched_at` desc limit ?', 'type' => 'select', 'connection' => 'mysql', 'weight' => 30, 'base_ms' => 1.7, 'jitter_ms' => 1.3],
            ['sql' => 'update `queue_job_runs` set `status` = ?, `completed_at` = ?, `duration_ms` = ?, `updated_at` = ? where `id` = ?', 'type' => 'update', 'connection' => 'mysql', 'weight' => 22, 'base_ms' => 1.4, 'jitter_ms' => 0.9],
            ['sql' => 'insert into `queue_job_runs` (`id`, `project_id`, `job_class`, `queue`, `connection`, `payload`, `status`, `dispatched_at`, `created_at`, `updated_at`) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', 'type' => 'insert', 'connection' => 'mysql', 'weight' => 28, 'base_ms' => 2.1, 'jitter_ms' => 1.5],
            ['sql' => 'select * from `command_runs` where `project_id` = ? and `command` = ? order by `occurred_at` desc limit ?', 'type' => 'select', 'connection' => 'mysql', 'weight' => 14, 'base_ms' => 1.6, 'jitter_ms' => 1.1],
            ['sql' => 'select * from `scheduled_task_runs` where `project_id` = ? and `task_hash` = ? order by `occurred_at` desc', 'type' => 'select', 'connection' => 'mysql', 'weight' => 12, 'base_ms' => 1.5, 'jitter_ms' => 1.0],
            ['sql' => 'select * from `sessions` where `id` = ? limit 1', 'type' => 'select', 'connection' => 'mysql', 'weight' => 110, 'base_ms' => 0.5, 'jitter_ms' => 0.3],
            ['sql' => 'update `sessions` set `payload` = ?, `last_activity` = ? where `id` = ?', 'type' => 'update', 'connection' => 'mysql', 'weight' => 95, 'base_ms' => 0.9, 'jitter_ms' => 0.4],
            ['sql' => 'insert into `sessions` (`id`, `user_id`, `payload`, `last_activity`) values (?, ?, ?, ?)', 'type' => 'insert', 'connection' => 'mysql', 'weight' => 18, 'base_ms' => 1.1, 'jitter_ms' => 0.5],
            ['sql' => 'delete from `sessions` where `last_activity` < ?', 'type' => 'delete', 'connection' => 'mysql', 'weight' => 4, 'base_ms' => 5.2, 'jitter_ms' => 3.6],
            ['sql' => 'select * from `cache` where `key` = ? and `expiration` > ? limit 1', 'type' => 'select', 'connection' => 'mysql', 'weight' => 75, 'base_ms' => 0.4, 'jitter_ms' => 0.3],
            ['sql' => 'insert into `cache` (`key`, `value`, `expiration`) values (?, ?, ?) on duplicate key update `value` = values(`value`), `expiration` = values(`expiration`)', 'type' => 'insert', 'connection' => 'mysql', 'weight' => 42, 'base_ms' => 0.9, 'jitter_ms' => 0.5],
            ['sql' => 'select count(*) as aggregate from `notifications` where `user_id` = ? and `read_at` is null', 'type' => 'select', 'connection' => 'mysql', 'weight' => 48, 'base_ms' => 0.9, 'jitter_ms' => 0.6],
            ['sql' => 'select * from `notifications` where `user_id` = ? order by `created_at` desc limit ?', 'type' => 'select', 'connection' => 'mysql', 'weight' => 32, 'base_ms' => 1.4, 'jitter_ms' => 1.1],
            ['sql' => 'update `notifications` set `read_at` = ?, `updated_at` = ? where `id` = ?', 'type' => 'update', 'connection' => 'mysql', 'weight' => 16, 'base_ms' => 1.0, 'jitter_ms' => 0.7],
            ['sql' => 'select * from `orders` where `user_id` = ? order by `created_at` desc', 'type' => 'select', 'connection' => 'mysql', 'weight' => 38, 'base_ms' => 2.5, 'jitter_ms' => 2.1],
            ['sql' => 'select `orders`.*, `users`.`email` from `orders` inner join `users` on `orders`.`user_id` = `users`.`id` where `orders`.`id` = ? limit 1', 'type' => 'select', 'connection' => 'mysql', 'weight' => 26, 'base_ms' => 2.8, 'jitter_ms' => 1.9],
            ['sql' => 'update `orders` set `status` = ?, `updated_at` = ? where `id` = ?', 'type' => 'update', 'connection' => 'mysql', 'weight' => 20, 'base_ms' => 1.6, 'jitter_ms' => 0.9],
            ['sql' => 'insert into `orders` (`user_id`, `total`, `currency`, `status`, `created_at`, `updated_at`) values (?, ?, ?, ?, ?, ?)', 'type' => 'insert', 'connection' => 'mysql', 'weight' => 18, 'base_ms' => 2.2, 'jitter_ms' => 1.5],
            ['sql' => 'select * from `order_items` where `order_id` = ?', 'type' => 'select', 'connection' => 'mysql', 'weight' => 36, 'base_ms' => 1.7, 'jitter_ms' => 1.2],
            ['sql' => 'select `products`.* from `products` where `products`.`id` in (?, ?, ?, ?)', 'type' => 'select', 'connection' => 'mysql', 'weight' => 28, 'base_ms' => 1.9, 'jitter_ms' => 1.3],
            ['sql' => 'select * from `products` where `slug` = ? limit 1', 'type' => 'select', 'connection' => 'mysql', 'weight' => 70, 'base_ms' => 0.9, 'jitter_ms' => 0.5],
            ['sql' => 'select * from `inventory` where `product_id` = ? and `warehouse_id` = ? for update', 'type' => 'select', 'connection' => 'mysql', 'weight' => 10, 'base_ms' => 6.8, 'jitter_ms' => 9.2],
            ['sql' => 'update `inventory` set `quantity` = `quantity` - ?, `updated_at` = ? where `product_id` = ? and `warehouse_id` = ?', 'type' => 'update', 'connection' => 'mysql', 'weight' => 8, 'base_ms' => 4.1, 'jitter_ms' => 5.6],
            ['sql' => 'select * from `audit_logs` where `subject_type` = ? and `subject_id` = ? order by `created_at` desc limit ?', 'type' => 'select', 'connection' => 'mysql', 'weight' => 14, 'base_ms' => 8.7, 'jitter_ms' => 12.1],
            ['sql' => 'insert into `audit_logs` (`user_id`, `subject_type`, `subject_id`, `event`, `properties`, `created_at`) values (?, ?, ?, ?, ?, ?)', 'type' => 'insert', 'connection' => 'mysql', 'weight' => 20, 'base_ms' => 2.4, 'jitter_ms' => 1.7],
            ['sql' => 'select * from `webhooks` where `project_id` = ? and `is_active` = ?', 'type' => 'select', 'connection' => 'mysql', 'weight' => 16, 'base_ms' => 0.8, 'jitter_ms' => 0.4],
            ['sql' => 'insert into `webhook_deliveries` (`webhook_id`, `payload`, `status`, `attempt`, `created_at`) values (?, ?, ?, ?, ?)', 'type' => 'insert', 'connection' => 'mysql', 'weight' => 14, 'base_ms' => 1.4, 'jitter_ms' => 0.9],
            ['sql' => 'select * from `feature_flags` where `key` = ? limit 1', 'type' => 'select', 'connection' => 'redis', 'weight' => 200, 'base_ms' => 0.2, 'jitter_ms' => 0.1],
            ['sql' => 'select * from `rate_limits` where `bucket` = ? and `period_start` >= ?', 'type' => 'select', 'connection' => 'redis', 'weight' => 140, 'base_ms' => 0.3, 'jitter_ms' => 0.2],
            ['sql' => 'incr `rate_limits:?:?`', 'type' => 'update', 'connection' => 'redis', 'weight' => 140, 'base_ms' => 0.2, 'jitter_ms' => 0.1],
            ['sql' => 'select `id`, `email` from `analytics`.`events` where `event` = ? and `created_at` between ? and ? order by `created_at` desc limit ?', 'type' => 'select', 'connection' => 'analytics', 'weight' => 9, 'base_ms' => 18.4, 'jitter_ms' => 24.6],
            ['sql' => 'select sum(`amount`) as `total` from `analytics`.`revenue_daily` where `tenant_id` = ? and `day` between ? and ?', 'type' => 'select', 'connection' => 'analytics', 'weight' => 7, 'base_ms' => 14.6, 'jitter_ms' => 18.2],
        ];

        $weightedIndex = $this->buildWeightedIndex($templates);

        $tracesByHour = [];
        foreach ($traces as $trace) {
            $hour = substr((string) $trace['occurred_at'], 0, 13);
            $tracesByHour[$hour][] = $trace;
        }

        $rows = [];
        foreach ($templates as $template) {
            $totalCalls = max(1, (int) ($template['weight'] * random_int(7, 12)));

            for ($i = 0; $i < $totalCalls; $i++) {
                $trace = $traces[array_rand($traces)];

                $duration = $template['base_ms'] + (random_int(0, (int) ($template['jitter_ms'] * 100)) / 100);
                if (random_int(1, 50) === 1) {
                    $duration *= random_int(3, 12);
                }

                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'project_id' => $project->id,
                    'trace_id' => $trace['id'],
                    'query_type' => $template['type'],
                    'sql' => $template['sql'],
                    'bindings' => json_encode([]),
                    'connection_name' => $template['connection'],
                    'duration_ms' => round($duration, 3),
                    'row_count' => $template['type'] === 'select' ? random_int(0, 50) : null,
                    'is_n_plus_one' => $totalCalls > 1200 && random_int(1, 4) === 1,
                    'is_slow' => $duration > 25,
                    'occurred_at' => $trace['occurred_at'],
                    'created_at' => $trace['occurred_at'],
                    'updated_at' => $trace['occurred_at'],
                ];

                if (count($rows) >= 500) {
                    TraceQuery::query()->insert($rows);
                    $rows = [];
                }
            }
        }

        // Filler queries to populate the long tail (low-weight templates picked at random).
        $filler = max(0, 800 - count($rows));
        for ($i = 0; $i < $filler; $i++) {
            $template = $templates[$weightedIndex[array_rand($weightedIndex)]];
            $trace = $traces[array_rand($traces)];
            $duration = $template['base_ms'] + (random_int(0, (int) ($template['jitter_ms'] * 100)) / 100);

            $rows[] = [
                'id' => (string) Str::uuid(),
                'project_id' => $project->id,
                'trace_id' => $trace['id'],
                'query_type' => $template['type'],
                'sql' => $template['sql'],
                'bindings' => json_encode([]),
                'connection_name' => $template['connection'],
                'duration_ms' => round($duration, 3),
                'row_count' => $template['type'] === 'select' ? random_int(0, 50) : null,
                'is_n_plus_one' => false,
                'is_slow' => $duration > 25,
                'occurred_at' => $trace['occurred_at'],
                'created_at' => $trace['occurred_at'],
                'updated_at' => $trace['occurred_at'],
            ];

            if (count($rows) >= 500) {
                TraceQuery::query()->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            TraceQuery::query()->insert($rows);
        }
    }

    /**
     * @param  list<array{weight:int}>  $templates
     * @return list<int>
     */
    private function buildWeightedIndex(array $templates): array
    {
        $index = [];
        foreach ($templates as $i => $template) {
            for ($j = 0; $j < $template['weight']; $j++) {
                $index[] = $i;
            }
        }

        return $index;
    }

    private function seedJobs(Project $project, CarbonImmutable $start): void
    {
        $jobClasses = [
            'App\\Events\\Backup\\BackupCreated',
            'App\\Events\\Backup\\BackupSuccess',
            'App\\Events\\Server\\ResourceMonitorStatsReceived',
            'App\\Events\\Site\\CertificateStatusUpdated',
            'App\\Events\\Site\\SiteActive',
            'App\\Events\\Site\\SiteCreationProgress',
            'App\\Events\\Site\\SiteDeleted',
            'App\\Jobs\\Certificate\\DeployScript',
            'App\\Jobs\\SendInvoiceEmail',
            'App\\Jobs\\ProcessPayment',
            'App\\Jobs\\GenerateReport',
            'App\\Jobs\\SyncCustomer',
            'App\\Jobs\\PruneAuditLog',
            'App\\Jobs\\IndexSearch',
            'App\\Jobs\\WarmCache',
            'App\\Jobs\\ExportCsv',
        ];

        $connections = ['redis', 'redis', 'redis', 'database', 'sqs'];
        $queues = ['default', 'default', 'default', 'high', 'low', 'emails'];

        // Weighted status distribution: mostly completed, some released/queued, rare failures.
        $statuses = [
            'completed', 'completed', 'completed', 'completed', 'completed',
            'completed', 'completed', 'completed', 'completed', 'completed',
            'released', 'released',
            'queued',
            'failed',
        ];

        $rows = [];
        for ($i = 0; $i < 600; $i++) {
            $dispatched = $start->addSeconds(random_int(0, 86_400));
            $status = $statuses[array_rand($statuses)];
            $duration = random_int(20, 6_200);

            $startedAt = $status === 'queued' ? null : $dispatched->addSeconds(random_int(0, 5));
            $completedAt = $status === 'completed' && $startedAt !== null ? $startedAt->addMilliseconds($duration) : null;
            $failedAt = $status === 'failed' && $startedAt !== null ? $startedAt->addMilliseconds($duration) : null;

            $rows[] = [
                'id' => (string) Str::uuid(),
                'project_id' => $project->id,
                'trace_id' => null,
                'job_class' => $jobClasses[array_rand($jobClasses)],
                'queue' => $queues[array_rand($queues)],
                'connection' => $connections[array_rand($connections)],
                'dispatched_at' => $dispatched,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'failed_at' => $failedAt,
                'duration_ms' => $status === 'queued' ? null : $duration,
                'attempts' => $status === 'failed' ? random_int(2, 3) : ($status === 'queued' ? 0 : 1),
                'status' => $status,
                'payload' => json_encode([]),
                'exception' => $status === 'failed' ? json_encode(['class' => 'RuntimeException', 'message' => 'demo failure']) : null,
                'environment' => 'production',
                'created_at' => $dispatched,
                'updated_at' => $completedAt ?? $failedAt ?? $dispatched,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            QueueJobRun::query()->insert($chunk);
        }
    }

    private function seedCommands(Project $project, CarbonImmutable $start): void
    {
        $commands = [
            ['command' => 'backup:daily', 'base_duration' => 4_200, 'fail_rate' => 5],
            ['command' => 'backup:hourly', 'base_duration' => 1_800, 'fail_rate' => 3],
            ['command' => 'backup:sweep', 'base_duration' => 320, 'fail_rate' => 1],
            ['command' => 'servers:cleanup-ssh-key-files', 'base_duration' => 145, 'fail_rate' => 2],
            ['command' => 'uploads:clear', 'base_duration' => 85, 'fail_rate' => 0],
            ['command' => 'alerts:cleanup-migration-failure-archives', 'base_duration' => 230, 'fail_rate' => 4],
            ['command' => 'queue:prune-batches', 'base_duration' => 60, 'fail_rate' => 1],
            ['command' => 'cache:prune-stale-tags', 'base_duration' => 28, 'fail_rate' => 0],
        ];

        $environments = ['production', 'production', 'production', 'staging'];

        $rows = [];
        for ($i = 0; $i < 420; $i++) {
            $tpl = $commands[array_rand($commands)];

            $jitter = random_int(-30, 220) / 100;
            $duration = max(5, (int) round($tpl['base_duration'] * (1 + $jitter)));

            $failed = random_int(1, 100) <= $tpl['fail_rate'];
            $exitCode = $failed ? [1, 1, 1, 2, 127][array_rand([1, 1, 1, 2, 127])] : 0;
            $status = $failed ? 'failed' : 'completed';

            $offsetSeconds = random_int(0, 86_400);
            $occurredAt = $start->addSeconds($offsetSeconds);

            $rows[] = [
                'id' => (string) Str::uuid(),
                'project_id' => $project->id,
                'command' => $tpl['command'],
                'arguments' => json_encode([]),
                'options' => json_encode(['--quiet' => false]),
                'status' => $status,
                'exit_code' => $exitCode,
                'duration_ms' => $duration,
                'output' => $failed
                    ? "Error: process exited with code {$exitCode}\n  at ".$tpl['command']
                    : "Completed {$tpl['command']} in {$duration}ms",
                'environment' => $environments[array_rand($environments)],
                'occurred_at' => $occurredAt,
                'created_at' => $occurredAt,
                'updated_at' => $occurredAt,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            CommandRun::query()->insert($chunk);
        }
    }

    private function seedScheduledTasks(Project $project, CarbonImmutable $start, CarbonImmutable $now): void
    {
        $tasks = [
            [
                'task' => 'php artisan backup:daily',
                'schedule' => 'Every hour',
                'schedule_summary' => 'AT 02:00 AM',
                'next_run_at' => $now->addMinutes(46),
                'base_duration' => 920,
                'fail_rate' => 5,
                'skip_rate' => 2,
            ],
            [
                'task' => 'php artisan backup:hourly',
                'schedule' => 'Every hour',
                'schedule_summary' => 'AT MINUTE 00',
                'next_run_at' => $now->addMinutes(46),
                'base_duration' => 540,
                'fail_rate' => 3,
                'skip_rate' => 5,
            ],
            [
                'task' => 'php artisan backup:sweep',
                'schedule' => 'Every 15 minutes',
                'schedule_summary' => 'EVERY 15 MINUTES',
                'next_run_at' => $now->addMinutes(1),
                'base_duration' => 18,
                'fail_rate' => 1,
                'skip_rate' => 0,
            ],
            [
                'task' => 'php artisan servers:cleanup-ssh-key-files',
                'schedule' => 'Every hour',
                'schedule_summary' => 'AT MINUTE 00',
                'next_run_at' => $now->addMinutes(46),
                'base_duration' => 1_680,
                'fail_rate' => 2,
                'skip_rate' => 0,
            ],
            [
                'task' => 'php artisan uploads:clear',
                'schedule' => 'At 25 minutes past the hour',
                'schedule_summary' => 'AT MINUTE 25',
                'next_run_at' => $now->addMinutes(11),
                'base_duration' => 466,
                'fail_rate' => 0,
                'skip_rate' => 0,
            ],
            [
                'task' => 'Closure at: routes/console.php:38',
                'schedule' => 'Every day at 02:00',
                'schedule_summary' => 'AT 02:00 AM',
                'next_run_at' => $now->addDay()->setTime(2, 0),
                'base_duration' => 14,
                'fail_rate' => 0,
                'skip_rate' => 0,
            ],
        ];

        $environments = ['production', 'production', 'production', 'staging'];

        $rows = [];
        foreach ($tasks as $tpl) {
            $runs = random_int(40, 90);
            $hash = sha1($tpl['task']);

            for ($i = 0; $i < $runs; $i++) {
                $jitter = random_int(-25, 180) / 100;
                $duration = max(2, (int) round($tpl['base_duration'] * (1 + $jitter)));

                $roll = random_int(1, 100);
                if ($roll <= $tpl['fail_rate']) {
                    $status = 'failed';
                    $exitCode = [1, 1, 2, 127][array_rand([1, 1, 2, 127])];
                } elseif ($roll <= $tpl['fail_rate'] + $tpl['skip_rate']) {
                    $status = 'skipped';
                    $exitCode = null;
                    $duration = null;
                } else {
                    $status = 'processed';
                    $exitCode = 0;
                }

                $offsetSeconds = random_int(0, 86_400);
                $occurredAt = $start->addSeconds($offsetSeconds);

                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'project_id' => $project->id,
                    'task' => $tpl['task'],
                    'task_hash' => $hash,
                    'schedule' => $tpl['schedule'],
                    'schedule_summary' => $tpl['schedule_summary'],
                    'next_run_at' => $tpl['next_run_at'],
                    'status' => $status,
                    'exit_code' => $exitCode,
                    'duration_ms' => $duration,
                    'threshold_ms' => null,
                    'output' => $status === 'failed'
                        ? "Error: process exited with code {$exitCode}"
                        : ($status === 'skipped' ? 'Skipped by withoutOverlapping()' : "Completed in {$duration}ms"),
                    'environment' => $environments[array_rand($environments)],
                    'occurred_at' => $occurredAt,
                    'created_at' => $occurredAt,
                    'updated_at' => $occurredAt,
                ];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            ScheduledTaskRun::query()->insert($chunk);
        }
    }

    private function seedMail(Project $project, CarbonImmutable $start): void
    {
        $mailables = [
            [
                'class' => 'App\\Mail\\OrderShipped',
                'subject' => 'Your order has shipped',
                'base_duration' => 280,
                'mailer' => 'smtp',
                'source_type' => 'job',
                'source_label' => 'App\\Jobs\\SendShipmentNotice',
            ],
            [
                'class' => 'App\\Mail\\WelcomeEmail',
                'subject' => 'Welcome to Acme',
                'base_duration' => 140,
                'mailer' => 'smtp',
                'source_type' => 'controller',
                'source_label' => 'App\\Http\\Controllers\\Auth\\RegisterController@store',
            ],
            [
                'class' => 'App\\Mail\\PasswordResetEmail',
                'subject' => 'Reset your password',
                'base_duration' => 90,
                'mailer' => 'smtp',
                'source_type' => 'controller',
                'source_label' => 'App\\Http\\Controllers\\Auth\\ForgotPasswordController@sendResetLinkEmail',
            ],
            [
                'class' => 'App\\Mail\\InvoiceReady',
                'subject' => 'Your invoice is ready',
                'base_duration' => 420,
                'mailer' => 'postmark',
                'source_type' => 'job',
                'source_label' => 'App\\Jobs\\GenerateInvoice',
            ],
            [
                'class' => 'App\\Mail\\WeeklyDigest',
                'subject' => 'Your weekly digest',
                'base_duration' => 1_240,
                'mailer' => 'ses',
                'source_type' => 'schedule',
                'source_label' => 'php artisan digest:weekly',
            ],
            [
                'class' => 'App\\Mail\\PaymentReceived',
                'subject' => 'Payment received',
                'base_duration' => 180,
                'mailer' => 'postmark',
                'source_type' => 'job',
                'source_label' => 'App\\Jobs\\ProcessPayment',
            ],
            [
                'class' => 'App\\Mail\\AccountSuspended',
                'subject' => 'Your account has been suspended',
                'base_duration' => 220,
                'mailer' => 'smtp',
                'source_type' => 'command',
                'source_label' => 'php artisan accounts:suspend',
            ],
        ];

        $environments = ['production', 'production', 'production', 'staging'];
        $rows = [];

        foreach ($mailables as $tpl) {
            $sends = random_int(40, 110);

            for ($i = 0; $i < $sends; $i++) {
                $jitter = random_int(-25, 220) / 100;
                $duration = max(8, (int) round($tpl['base_duration'] * (1 + $jitter)));

                $recipientsCount = random_int(1, 4);
                $attachments = random_int(0, 100) <= 15 ? random_int(1, 3) : 0;

                $offsetSeconds = random_int(0, 86_400);
                $occurredAt = $start->addSeconds($offsetSeconds);

                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'project_id' => $project->id,
                    'trace_id' => null,
                    'mailable_class' => $tpl['class'],
                    'mailer' => $tpl['mailer'],
                    'subject' => $tpl['subject'],
                    'from_address' => 'no-reply@acme.test',
                    'from_name' => 'Acme',
                    'recipients_to' => null,
                    'recipients_cc' => null,
                    'recipients_bcc' => null,
                    'recipients_count' => $recipientsCount,
                    'attachments_count' => $attachments,
                    'queue' => $tpl['source_type'] === 'job' ? 'mail' : null,
                    'status' => 'sent',
                    'duration_ms' => $duration,
                    'source_type' => $tpl['source_type'],
                    'source_id' => null,
                    'source_label' => $tpl['source_label'],
                    'environment' => $environments[array_rand($environments)],
                    'occurred_at' => $occurredAt,
                    'created_at' => $occurredAt,
                    'updated_at' => $occurredAt,
                ];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            MailSend::query()->insert($chunk);
        }
    }

    private function seedNotifications(Project $project, CarbonImmutable $start): void
    {
        $notifications = [
            [
                'class' => 'App\\Notifications\\OrderShippedNotification',
                'channels' => ['mail', 'database'],
                'base_duration' => 240,
                'source_type' => 'job',
                'source_label' => 'App\\Jobs\\ShipOrder',
            ],
            [
                'class' => 'App\\Notifications\\PaymentFailedNotification',
                'channels' => ['mail', 'slack', 'database'],
                'base_duration' => 320,
                'source_type' => 'job',
                'source_label' => 'App\\Jobs\\ProcessPayment',
            ],
            [
                'class' => 'App\\Notifications\\NewCommentNotification',
                'channels' => ['database', 'broadcast'],
                'base_duration' => 60,
                'source_type' => 'controller',
                'source_label' => 'App\\Http\\Controllers\\CommentsController@store',
            ],
            [
                'class' => 'App\\Notifications\\TwoFactorCodeNotification',
                'channels' => ['mail', 'nexmo'],
                'base_duration' => 140,
                'source_type' => 'controller',
                'source_label' => 'App\\Http\\Controllers\\Auth\\TwoFactorController@send',
            ],
            [
                'class' => 'App\\Notifications\\SystemAlertNotification',
                'channels' => ['slack'],
                'base_duration' => 410,
                'source_type' => 'schedule',
                'source_label' => 'php artisan monitor:alerts',
            ],
            [
                'class' => 'App\\Notifications\\InvoiceOverdueNotification',
                'channels' => ['mail', 'database'],
                'base_duration' => 280,
                'source_type' => 'schedule',
                'source_label' => 'php artisan invoices:remind',
            ],
        ];

        $environments = ['production', 'production', 'production', 'staging'];
        $rows = [];

        foreach ($notifications as $tpl) {
            $base = random_int(40, 110);

            for ($i = 0; $i < $base; $i++) {
                foreach ($tpl['channels'] as $channel) {
                    $jitter = random_int(-30, 220) / 100;
                    $duration = max(5, (int) round($tpl['base_duration'] * (1 + $jitter)));

                    $offsetSeconds = random_int(0, 86_400);
                    $occurredAt = $start->addSeconds($offsetSeconds);

                    $rows[] = [
                        'id' => (string) Str::uuid(),
                        'project_id' => $project->id,
                        'trace_id' => null,
                        'notification_class' => $tpl['class'],
                        'channel' => $channel,
                        'notifiable_type' => 'App\\Models\\User',
                        'notifiable_id' => (string) random_int(1, 1000),
                        'queue' => $tpl['source_type'] === 'job' ? 'notifications' : null,
                        'status' => 'sent',
                        'duration_ms' => $duration,
                        'source_type' => $tpl['source_type'],
                        'source_id' => null,
                        'source_label' => $tpl['source_label'],
                        'environment' => $environments[array_rand($environments)],
                        'occurred_at' => $occurredAt,
                        'created_at' => $occurredAt,
                        'updated_at' => $occurredAt,
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            NotificationSend::query()->insert($chunk);
        }
    }
}
