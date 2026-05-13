<?php

namespace Database\Seeders;

use App\Models\ErrorGroup;
use App\Models\ErrorOccurrence;
use App\Models\Organization;
use App\Models\Project;
use App\Models\QueueJobRun;
use App\Models\Trace;
use App\Models\TraceQuery;
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

            $traces[] = [
                'id' => (string) Str::uuid(),
                'project_id' => $project->id,
                'correlation_id' => (string) Str::uuid(),
                'method' => $method,
                'uri' => $uri,
                'status_code' => $status,
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

        $this->seedExceptions($project, $traces, $start);
        $this->seedQueries($project, $traces);
        $this->seedJobs($project, $start);
    }

    /**
     * @param  list<array<string, mixed>>  $traces
     */
    private function seedExceptions(Project $project, array $traces, CarbonImmutable $start): void
    {
        $templates = [
            [
                'class' => 'Illuminate\\Database\\QueryException',
                'message' => 'SQLSTATE[42S22]: Column not found: orders.status',
                'file' => 'app/Repositories/OrderRepository.php',
                'line' => 84,
            ],
            [
                'class' => 'GuzzleHttp\\Exception\\ConnectException',
                'message' => 'cURL error 28: Connection timed out after 30000 ms',
                'file' => 'app/Services/PaymentGateway.php',
                'line' => 122,
            ],
            [
                'class' => 'TypeError',
                'message' => 'Argument #1 ($id) must be of type int, string given',
                'file' => 'app/Http/Controllers/UserController.php',
                'line' => 56,
            ],
            [
                'class' => 'Symfony\\Component\\HttpKernel\\Exception\\NotFoundHttpException',
                'message' => 'The route api/legacy could not be found.',
                'file' => 'vendor/symfony/http-kernel/HttpKernel.php',
                'line' => 138,
            ],
            [
                'class' => 'RuntimeException',
                'message' => 'Failed to dispatch InvoiceMailer: queue connection unavailable',
                'file' => 'app/Jobs/SendInvoice.php',
                'line' => 41,
            ],
        ];

        $errorTraces = array_values(array_filter($traces, fn (array $t): bool => $t['has_errors']));
        $errorRows = [];
        $groups = [];
        $fingerprinter = new Fingerprinter;

        $errorCount = max(80, count($errorTraces) * 2);

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
                    'exception_class' => $tpl['class'],
                    'first_message' => $tpl['message'],
                    'first_file' => $tpl['file'],
                    'first_line' => $tpl['line'],
                    'total_count' => 0,
                    'first_occurrence_at' => $occurredAt,
                    'last_occurrence_at' => $occurredAt,
                    'status' => 'unresolved',
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
                'stacktrace' => json_encode([
                    ['file' => $tpl['file'], 'line' => $tpl['line'], 'function' => 'handle'],
                    ['file' => 'app/Http/Kernel.php', 'line' => 12, 'function' => 'dispatch'],
                ]),
                'fingerprint' => $fingerprint,
                'file' => $tpl['file'],
                'line' => $tpl['line'],
                'environment' => 'production',
                'release_version' => 'v1.4.2',
                'context' => json_encode([]),
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
        $queries = [
            'SELECT * FROM users WHERE id = ?',
            'SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC',
            'UPDATE orders SET status = ? WHERE id = ?',
            'INSERT INTO sessions (id, user_id, payload) VALUES (?, ?, ?)',
            'SELECT count(*) FROM notifications WHERE user_id = ? AND read_at IS NULL',
        ];

        $rows = [];
        foreach ($traces as $trace) {
            $count = (int) $trace['db_queries_count'];
            for ($i = 0; $i < $count; $i++) {
                $sql = $queries[array_rand($queries)];
                $duration = random_int(1, 220) / 10;
                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'project_id' => $project->id,
                    'trace_id' => $trace['id'],
                    'query_type' => str_starts_with($sql, 'SELECT') ? 'select' : (str_starts_with($sql, 'INSERT') ? 'insert' : 'update'),
                    'sql' => $sql,
                    'bindings' => json_encode([]),
                    'connection_name' => 'mysql',
                    'duration_ms' => $duration,
                    'row_count' => random_int(0, 50),
                    'is_n_plus_one' => $count > 12,
                    'is_slow' => $duration > 15,
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

        if ($rows !== []) {
            TraceQuery::query()->insert($rows);
        }
    }

    private function seedJobs(Project $project, CarbonImmutable $start): void
    {
        $jobClasses = [
            'App\\Jobs\\SendInvoiceEmail',
            'App\\Jobs\\ProcessPayment',
            'App\\Jobs\\GenerateReport',
            'App\\Jobs\\SyncCustomer',
            'App\\Jobs\\PruneAuditLog',
        ];
        $statuses = ['completed', 'completed', 'completed', 'completed', 'failed', 'released'];

        $rows = [];
        for ($i = 0; $i < 320; $i++) {
            $dispatched = $start->addSeconds(random_int(0, 86_400));
            $startedAt = $dispatched->addSeconds(random_int(0, 5));
            $duration = random_int(40, 4_200);
            $status = $statuses[array_rand($statuses)];
            $completedAt = $status === 'completed' ? $startedAt->addMilliseconds($duration) : null;
            $failedAt = $status === 'failed' ? $startedAt->addMilliseconds($duration) : null;

            $rows[] = [
                'id' => (string) Str::uuid(),
                'project_id' => $project->id,
                'trace_id' => null,
                'job_class' => $jobClasses[array_rand($jobClasses)],
                'queue' => 'default',
                'connection' => 'redis',
                'dispatched_at' => $dispatched,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'failed_at' => $failedAt,
                'duration_ms' => $duration,
                'attempts' => $status === 'failed' ? random_int(2, 3) : 1,
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
}
