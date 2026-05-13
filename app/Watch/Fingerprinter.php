<?php

namespace App\Watch;

class Fingerprinter
{
    /**
     * Build a deterministic fingerprint for an exception payload so that
     * occurrences sharing class + location collapse into a single ErrorGroup.
     *
     * @param  array<string, mixed>  $exception
     */
    public function forException(array $exception): string
    {
        $class = (string) ($exception['class'] ?? $exception['exception_class'] ?? 'UnknownException');
        $file = (string) ($exception['file'] ?? $this->firstFrameValue($exception, 'file') ?? '');
        $line = (string) ($exception['line'] ?? $this->firstFrameValue($exception, 'line') ?? '');

        return hash('sha256', $class.'|'.$file.'|'.$line);
    }

    /**
     * @param  array<string, mixed>  $exception
     */
    private function firstFrameValue(array $exception, string $key): ?string
    {
        $stack = $exception['stacktrace'] ?? $exception['trace'] ?? [];

        if (! is_array($stack) || $stack === []) {
            return null;
        }

        $first = $stack[0];

        return is_array($first) && isset($first[$key]) ? (string) $first[$key] : null;
    }
}
