<?php

declare(strict_types=1);

namespace App\Services\Metrics;

use RuntimeException;

final class MetricsService
{
    /**
     * @return array{
     *     total_requests: int,
     *     successful_requests: int,
     *     failed_requests: int,
     *     ai_fallbacks: int
     * }
     */
    public function get(): array
    {
        return $this->withLockedMetricsFile(
            static fn (array $metrics): array => $metrics,
            false,
        );
    }

    public function incrementTotalRequests(): void
    {
        $this->increment('total_requests');
    }

    public function incrementSuccessfulRequests(): void
    {
        $this->increment('successful_requests');
    }

    public function incrementFailedRequests(): void
    {
        $this->increment('failed_requests');
    }

    public function incrementAiFallbacks(): void
    {
        $this->increment('ai_fallbacks');
    }

    private function increment(string $key): void
    {
        $this->withLockedMetricsFile(static function (array $metrics) use ($key): array {
            $metrics[$key]++;

            return $metrics;
        });
    }

    /**
     * @param  callable(array{
     *     total_requests: int,
     *     successful_requests: int,
     *     failed_requests: int,
     *     ai_fallbacks: int
     * }): array{
     *     total_requests: int,
     *     successful_requests: int,
     *     failed_requests: int,
     *     ai_fallbacks: int
     * }  $callback
     * @return array{
     *     total_requests: int,
     *     successful_requests: int,
     *     failed_requests: int,
     *     ai_fallbacks: int
     * }
     */
    private function withLockedMetricsFile(callable $callback, bool $write = true): array
    {
        $path = $this->resolvePath();
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create metrics directory.');
        }

        $handle = fopen($path, 'c+');

        if ($handle === false) {
            throw new RuntimeException('Unable to open metrics file.');
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException('Unable to lock metrics file.');
            }

            rewind($handle);
            $contents = stream_get_contents($handle);
            $metrics = $this->decodeMetrics(is_string($contents) ? $contents : '');
            $updatedMetrics = $callback($metrics);

            if ($write) {
                rewind($handle);
                ftruncate($handle, 0);
                fwrite($handle, json_encode($updatedMetrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                fflush($handle);
            }

            flock($handle, LOCK_UN);

            return $updatedMetrics;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array{
     *     total_requests: int,
     *     successful_requests: int,
     *     failed_requests: int,
     *     ai_fallbacks: int
     * }
     */
    private function decodeMetrics(string $contents): array
    {
        if (trim($contents) === '') {
            return $this->defaultMetrics();
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            return $this->defaultMetrics();
        }

        $defaults = $this->defaultMetrics();

        return [
            'total_requests' => (int) ($decoded['total_requests'] ?? $defaults['total_requests']),
            'successful_requests' => (int) ($decoded['successful_requests'] ?? $defaults['successful_requests']),
            'failed_requests' => (int) ($decoded['failed_requests'] ?? $defaults['failed_requests']),
            'ai_fallbacks' => (int) ($decoded['ai_fallbacks'] ?? $defaults['ai_fallbacks']),
        ];
    }

    /**
     * @return array{
     *     total_requests: int,
     *     successful_requests: int,
     *     failed_requests: int,
     *     ai_fallbacks: int
     * }
     */
    private function defaultMetrics(): array
    {
        return [
            'total_requests' => 0,
            'successful_requests' => 0,
            'failed_requests' => 0,
            'ai_fallbacks' => 0,
        ];
    }

    private function resolvePath(): string
    {
        return (string) config('metrics.path', storage_path('app/metrics/contact-ai-api.json'));
    }
}
