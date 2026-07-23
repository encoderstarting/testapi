<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ContactTest extends TestCase
{
    public function test_it_creates_a_contact_request(): void
    {
        config([
            'services.ai.provider' => 'openai',
            'services.ai.api_key' => 'test-key',
            'services.ai.model' => 'gpt-4.1-mini',
            'services.ai.timeout' => 10,
        ]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'category' => 'project_request',
                                'sentiment' => 'positive',
                                'priority' => 'high',
                                'summary' => 'Клиент хочет обсудить разработку интернет-магазина.',
                            ], JSON_UNESCAPED_UNICODE),
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $this->postJson('/api/contact', [
            'name' => 'Никита',
            'phone' => '+79999999999',
            'email' => 'nikita@example.com',
            'comment' => 'Хочу обсудить разработку интернет-магазина.',
        ]);

        $response
            ->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Обращение успешно отправлено.',
                'data' => [
                    'category' => 'project_request',
                    'sentiment' => 'positive',
                    'priority' => 'high',
                    'processed_by_ai' => true,
                ],
            ]);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && $request->hasHeader('Authorization', ['Bearer test-key']);
        });
    }

    public function test_it_returns_validation_errors_for_empty_fields(): void
    {
        $response = $this->postJson('/api/contact', [
            'name' => '',
            'phone' => '',
            'email' => '',
            'comment' => '',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Данные не прошли проверку.')
            ->assertJsonValidationErrors([
                'name',
                'phone',
                'email',
                'comment',
            ]);
    }

    public function test_it_returns_validation_error_for_invalid_email(): void
    {
        $response = $this->postJson('/api/contact', [
            'name' => 'Никита',
            'phone' => '+79999999999',
            'email' => 'not-an-email',
            'comment' => 'Хочу обсудить разработку интернет-магазина.',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_it_returns_validation_error_for_invalid_phone(): void
    {
        $response = $this->postJson('/api/contact', [
            'name' => 'Никита',
            'phone' => 'abc123',
            'email' => 'nikita@example.com',
            'comment' => 'Хочу обсудить разработку интернет-магазина.',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_it_returns_validation_error_for_too_short_comment(): void
    {
        $response = $this->postJson('/api/contact', [
            'name' => 'Никита',
            'phone' => '+79999999999',
            'email' => 'nikita@example.com',
            'comment' => 'Слишком.',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['comment']);
    }

    public function test_it_uses_fallback_when_ai_returns_invalid_json(): void
    {
        config([
            'services.ai.provider' => 'openai',
            'services.ai.api_key' => 'test-key',
            'services.ai.model' => 'gpt-4.1-mini',
            'services.ai.timeout' => 10,
        ]);

        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'not-json',
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $this->postJson('/api/contact', [
            'name' => 'Никита',
            'phone' => '+79999999999',
            'email' => 'nikita@example.com',
            'comment' => 'Хочу обсудить разработку интернет-магазина.',
        ]);

        $response
            ->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Обращение успешно отправлено.',
                'data' => [
                    'category' => 'other',
                    'sentiment' => 'neutral',
                    'priority' => 'medium',
                    'processed_by_ai' => false,
                ],
            ]);
    }
}
