<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\TestCase;

final class DocumentationTest extends TestCase
{
    public function test_openapi_endpoint_returns_documented_paths(): void
    {
        $response = $this->getJson('/api/openapi.json');

        $response
            ->assertOk()
            ->assertJsonPath('openapi', '3.1.0')
            ->assertJsonPath('info.title', 'Contact AI API')
            ->assertJsonPath('paths./api/contact.post.summary', 'Create contact request')
            ->assertJsonPath('paths./api/health.get.summary', 'Application health status')
            ->assertJsonPath('paths./api/metrics.get.summary', 'Application metrics')
            ->assertJsonPath('servers.0.url', config('app.url'));
    }

    public function test_documentation_page_is_available(): void
    {
        $response = $this->get('/api/documentation');

        $response
            ->assertOk()
            ->assertSee('swagger-ui', false)
            ->assertSee('SwaggerUIBundle', false);
    }

    public function test_documentation_page_uses_https_url_behind_trusted_proxy(): void
    {
        $response = $this->withHeaders([
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'contact-api.up.railway.app',
            'X-Forwarded-Port' => '443',
        ])->get('/api/documentation');

        $response
            ->assertOk()
            ->assertSee('https:\\/\\/contact-api.up.railway.app\\/api\\/openapi.json', false);
    }
}
