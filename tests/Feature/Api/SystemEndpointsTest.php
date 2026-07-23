<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class SystemEndpointsTest extends TestCase
{
    public function test_health_endpoint_returns_service_statuses(): void
    {
        config([
            'contact.owner_email' => 'owner@example.com',
            'services.ai.provider' => 'gemini',
            'services.ai.api_key' => 'test-key',
            'services.ai.model' => 'gemini-3.5-flash-lite',
            'mail.default' => 'log',
            'mail.from.address' => 'hello@example.com',
        ]);

        $response = $this->getJson('/api/health');

        $response
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('services.application', 'available')
            ->assertJsonPath('services.ai', 'configured')
            ->assertJsonPath('services.mail', 'configured');

        $this->assertIsString($response->json('timestamp'));
    }

    public function test_metrics_endpoint_returns_zeroed_metrics_by_default(): void
    {
        $response = $this->getJson('/api/metrics');

        $response
            ->assertOk()
            ->assertExactJson([
                'total_requests' => 0,
                'successful_requests' => 0,
                'failed_requests' => 0,
                'ai_fallbacks' => 0,
            ]);
    }

    public function test_metrics_endpoint_reflects_successful_requests_and_fallbacks(): void
    {
        config([
            'contact.owner_email' => 'owner@example.com',
            'services.ai.provider' => 'gemini',
            'services.ai.api_key' => 'test-key',
            'services.ai.model' => 'gemini-3.5-flash-lite',
            'services.ai.timeout' => 10,
        ]);

        Mail::fake();

        Http::fakeSequence()
            ->push([
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
            ])
            ->push([
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
            ]);

        $payload = [
            'name' => 'Никита',
            'phone' => '+79999999999',
            'email' => 'nikita@example.com',
            'comment' => 'Хочу обсудить разработку интернет-магазина.',
        ];

        $this->postJson('/api/contact', $payload)->assertCreated();
        $this->postJson('/api/contact', $payload)->assertCreated();

        $response = $this->getJson('/api/metrics');

        $response
            ->assertOk()
            ->assertExactJson([
                'total_requests' => 2,
                'successful_requests' => 2,
                'failed_requests' => 0,
                'ai_fallbacks' => 1,
            ]);
    }

    public function test_metrics_endpoint_reflects_failed_mail_delivery(): void
    {
        config([
            'contact.owner_email' => 'owner@example.com',
            'services.ai.provider' => 'gemini',
            'services.ai.api_key' => 'test-key',
            'services.ai.model' => 'gemini-3.5-flash-lite',
            'services.ai.timeout' => 10,
        ]);

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

        Mail::swap(new class
        {
            public function to(mixed $users): self
            {
                return $this;
            }

            public function send(mixed $mailable): void
            {
                throw new \RuntimeException('SMTP failure');
            }
        });

        $this->postJson('/api/contact', [
            'name' => 'Никита',
            'phone' => '+79999999999',
            'email' => 'nikita@example.com',
            'comment' => 'Хочу обсудить разработку интернет-магазина.',
        ])->assertStatus(503);

        $response = $this->getJson('/api/metrics');

        $response
            ->assertOk()
            ->assertExactJson([
                'total_requests' => 1,
                'successful_requests' => 0,
                'failed_requests' => 1,
                'ai_fallbacks' => 0,
            ]);
    }
}
