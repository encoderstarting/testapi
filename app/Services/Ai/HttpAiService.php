<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\DTO\AiAnalysisResult;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;

final class HttpAiService implements AiServiceInterface
{
    public function analyze(string $comment): AiAnalysisResult
    {
        $provider = (string) config('services.ai.provider');
        $apiKey = (string) config('services.ai.api_key');
        $model = (string) config('services.ai.model');
        $timeout = (int) config('services.ai.timeout', 10);

        if ($provider === '' || $apiKey === '' || $model === '') {
            throw new RuntimeException('AI service is not configured.');
        }

        $response = match ($provider) {
            'openai' => $this->sendOpenAiRequest($comment, $apiKey, $model, $timeout),
            'gemini' => $this->sendGeminiRequest($comment, $apiKey, $model, $timeout),
            default => throw new RuntimeException('Unsupported AI provider.'),
        };

        if ($response->failed()) {
            throw new RuntimeException(
                sprintf('AI provider request failed with status %d.', $response->status())
            );
        }

        $content = match ($provider) {
            'openai' => data_get($response->json(), 'choices.0.message.content'),
            'gemini' => data_get($response->json(), 'candidates.0.content.parts.0.text'),
            default => null,
        };

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException('AI provider returned an empty response.');
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode(
                $this->stripMarkdownCodeFence($content),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('AI provider returned invalid JSON.', previous: $exception);
        }

        return AiAnalysisResult::fromArray($decoded);
    }

    private function sendOpenAiRequest(
        string $comment,
        string $apiKey,
        string $model,
        int $timeout,
    ): Response {
        return Http::acceptJson()
            ->asJson()
            ->withToken($apiKey)
            ->timeout($timeout)
            ->retry(2, 200)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $model,
                'temperature' => 0,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->buildPrompt($comment),
                    ],
                ],
            ]);
    }

    private function sendGeminiRequest(
        string $comment,
        string $apiKey,
        string $model,
        int $timeout,
    ): Response {
        return Http::acceptJson()
            ->asJson()
            ->timeout($timeout)
            ->retry(2, 200)
            ->post(
                sprintf(
                    'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
                    $model,
                    $apiKey,
                ),
                [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => $this->buildPrompt($comment),
                                ],
                            ],
                        ],
                    ],
                ]
            );
    }

    private function buildPrompt(string $comment): string
    {
        return <<<PROMPT
Проанализируй обращение пользователя.

Верни только JSON без markdown и дополнительного текста.

Формат ответа:

{
  "category": "project_request|support|cooperation|question|other",
  "sentiment": "positive|neutral|negative",
  "priority": "low|medium|high",
  "summary": "Краткое описание обращения до 150 символов"
}

Обращение пользователя:
{$comment}
PROMPT;
    }

    private function stripMarkdownCodeFence(string $content): string
    {
        $trimmed = trim($content);

        if (! str_starts_with($trimmed, '```')) {
            return $trimmed;
        }

        $trimmed = preg_replace('/^```(?:json)?\s*/', '', $trimmed);
        $trimmed = preg_replace('/\s*```$/', '', (string) $trimmed);

        return trim((string) $trimmed);
    }
}
