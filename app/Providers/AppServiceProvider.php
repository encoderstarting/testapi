<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Ai\AiServiceInterface;
use App\Services\Ai\HttpAiService;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AiServiceInterface::class, HttpAiService::class);
    }

    public function boot(): void
    {
        //
    }
}
