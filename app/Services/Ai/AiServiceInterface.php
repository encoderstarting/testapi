<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\DTO\AiAnalysisResult;

interface AiServiceInterface
{
    public function analyze(string $comment): AiAnalysisResult;
}
