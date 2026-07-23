<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

final class DocumentationController extends Controller
{
    public function index(): View
    {
        return view('documentation.index', [
            'specUrl' => url('/api/openapi.json'),
        ]);
    }

    public function openApi(): JsonResponse
    {
        /** @var array<string, mixed> $spec */
        $spec = config('openapi');
        $spec['servers'] = [
            [
                'url' => rtrim((string) config('app.url'), '/'),
                'description' => 'Current application URL',
            ],
        ];

        return response()->json($spec);
    }
}
