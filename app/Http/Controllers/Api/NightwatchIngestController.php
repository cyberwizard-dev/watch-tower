<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWatchEvent;
use App\Models\Project;
use App\Watch\NightwatchTranslator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NightwatchIngestController extends Controller
{
    public function __construct(private readonly NightwatchTranslator $translator) {}

    /**
     * Accept a gzipped batch of Nightwatch wire records, translate, and queue.
     */
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Project $project */
        $project = $request->attributes->get('watch_project');

        $body = $this->readBody($request);

        if ($body === null) {
            return $this->error('Unable to decode payload', status: 400);
        }

        try {
            $decoded = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $this->error('Invalid JSON payload: '.$e->getMessage(), status: 400);
        }

        $records = $decoded['records'] ?? null;

        if (! is_array($records)) {
            return $this->error('Missing records array', status: 422);
        }

        $received = count($records);
        $queued = 0;

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $event = $this->translator->translate($record);

            if ($event === null) {
                continue;
            }

            ProcessWatchEvent::dispatch($project->id, $event);
            $queued++;
        }

        return response()->json([
            'records_received' => $received,
            'records_queued' => $queued,
        ]);
    }

    private function readBody(Request $request): ?string
    {
        $raw = $request->getContent();

        if ($raw === '' || $raw === false) {
            return '';
        }

        $encoding = strtolower((string) $request->header('Content-Encoding', ''));

        if (str_contains($encoding, 'gzip')) {
            $decoded = @gzdecode($raw);

            return $decoded === false ? null : $decoded;
        }

        return $raw;
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'stop' => false,
        ], $status);
    }
}
