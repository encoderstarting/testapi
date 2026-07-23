<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Metrics\MetricsService;
use Illuminate\Http\JsonResponse;

final class MetricsController extends Controller
{
    public function __invoke(MetricsService $metricsService): JsonResponse
    {
        return response()->json($metricsService->get());
    }
}
