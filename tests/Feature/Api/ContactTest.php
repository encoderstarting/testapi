<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Mail\ContactOwnerMail;
use App\Mail\ContactUserMail;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

final class ContactTest extends TestCase
{
    public function test_it_creates_a_contact_request(): void
    {
        $this->configureAi();
        $this->fakeSuccessfulAiResponse();
        Mail::fake();

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
            return $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent?key=test-key';
        });

        Mail::assertSent(ContactOwnerMail::class, 1);
        Mail::assertSent(ContactUserMail::class, 1);
    }

    public function test_it_sends_a_mail_to_the_owner(): void
    {
        $this->configureAi();
        $this->fakeSuccessfulAiResponse();
        Mail::fake();

        $this->postJson('/api/contact', [
            'name' => 'Никита',
            'phone' => '+79999999999',
            'email' => 'nikita@example.com',
            'comment' => 'Хочу обсудить разработку интернет-магазина.',
        ])->assertCreated();

        Mail::assertSent(ContactOwnerMail::class, function (ContactOwnerMail $mail): bool {
            return $mail->hasTo('owner@example.com')
                && $mail->contactData->email === 'nikita@example.com'
                && $mail->analysisResult->processedByAi;
        });
    }

    public function test_it_sends_a_mail_to_the_user(): void
    {
        $this->configureAi();
        $this->fakeSuccessfulAiResponse();
        Mail::fake();

        $this->postJson('/api/contact', [
            'name' => 'Никита',
            'phone' => '+79999999999',
            'email' => 'nikita@example.com',
            'comment' => 'Хочу обсудить разработку интернет-магазина.',
        ])->assertCreated();

        Mail::assertSent(ContactUserMail::class, function (ContactUserMail $mail): bool {
            return $mail->hasTo('nikita@example.com')
                && $mail->contactData->name === 'Никита';
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
        $this->configureAi();
        Mail::fake();

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => 'not-json',
                                ],
                            ],
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

        Mail::assertSent(ContactOwnerMail::class, function (ContactOwnerMail $mail): bool {
            return $mail->analysisResult->processedByAi === false;
        });
    }

    public function test_it_returns_service_unavailable_when_mail_delivery_fails(): void
    {
        $this->configureAi();
        $this->fakeSuccessfulAiResponse();

        Mail::swap(new class
        {
            public function to(mixed $users): self
            {
                return $this;
            }

            public function send(mixed $mailable): void
            {
                throw new RuntimeException('SMTP failure');
            }
        });

        $response = $this->postJson('/api/contact', [
            'name' => 'Никита',
            'phone' => '+79999999999',
            'email' => 'nikita@example.com',
            'comment' => 'Хочу обсудить разработку интернет-магазина.',
        ]);

        $response
            ->assertStatus(503)
            ->assertJson([
                'success' => false,
                'message' => 'Сервис отправки сообщений временно недоступен.',
            ]);
    }

    public function test_it_returns_too_many_requests_after_five_requests(): void
    {
        $this->configureAi();
        $this->fakeSuccessfulAiResponse();
        Mail::fake();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/contact', [
                'name' => 'Никита',
                'phone' => '+79999999999',
                'email' => 'nikita@example.com',
                'comment' => 'Хочу обсудить разработку интернет-магазина.',
            ])->assertCreated();
        }

        $response = $this->postJson('/api/contact', [
            'name' => 'Никита',
            'phone' => '+79999999999',
            'email' => 'nikita@example.com',
            'comment' => 'Хочу обсудить разработку интернет-магазина.',
        ]);

        $response
            ->assertStatus(429)
            ->assertJson([
                'success' => false,
                'message' => 'Слишком много запросов. Попробуйте позже.',
            ]);
    }

    public function test_it_applies_cors_headers_for_allowed_frontend_origin(): void
    {
        $this->configureAi();
        $this->fakeSuccessfulAiResponse();
        Mail::fake();

        $response = $this->withHeaders([
            'Origin' => 'http://localhost:3000',
        ])->postJson('/api/contact', [
            'name' => 'Никита',
            'phone' => '+79999999999',
            'email' => 'nikita@example.com',
            'comment' => 'Хочу обсудить разработку интернет-магазина.',
        ]);

        $response
            ->assertCreated()
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
    }

    public function test_it_logs_contact_request_start_and_completion(): void
    {
        $this->configureAi();
        $this->fakeSuccessfulAiResponse();
        Mail::fake();
        Log::spy();

        $this->postJson('/api/contact', [
            'name' => 'Никита',
            'phone' => '+79999999999',
            'email' => 'nikita@example.com',
            'comment' => 'Хочу обсудить разработку интернет-магазина.',
        ])->assertCreated();

        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            return $message === 'Contact request started.'
                && $context['path'] === 'api/contact';
        })->once();

        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context): bool {
            return $message === 'Contact request completed.'
                && $context['path'] === 'api/contact'
                && $context['status'] === 201
                && array_key_exists('duration_ms', $context);
        })->once();
    }

    private function configureAi(): void
    {
        config([
            'contact.owner_email' => 'owner@example.com',
            'cors.allowed_origins' => ['http://localhost:3000'],
            'services.ai.provider' => 'gemini',
            'services.ai.api_key' => 'test-key',
            'services.ai.model' => 'gemini-3.5-flash-lite',
            'services.ai.timeout' => 10,
        ]);
    }

    private function fakeSuccessfulAiResponse(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'category' => 'project_request',
                                        'sentiment' => 'positive',
                                        'priority' => 'high',
                                        'summary' => 'Клиент хочет обсудить разработку интернет-магазина.',
                                    ], JSON_UNESCAPED_UNICODE),
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);
    }
}
