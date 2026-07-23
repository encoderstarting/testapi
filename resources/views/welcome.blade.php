<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Contact AI API') }}</title>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <style>
                body {
                    margin: 0;
                    font-family: Arial, sans-serif;
                    background: #f5f1e8;
                    color: #201914;
                }

                .fallback {
                    max-width: 880px;
                    margin: 0 auto;
                    padding: 48px 24px;
                }

                .fallback__panel {
                    padding: 32px;
                    border: 1px solid rgba(0, 0, 0, 0.08);
                    border-radius: 24px;
                    background: rgba(255, 251, 245, 0.95);
                }

                .fallback__eyebrow {
                    margin: 0 0 12px;
                    font-size: 12px;
                    letter-spacing: 0.12em;
                    text-transform: uppercase;
                    color: #9a4a26;
                    font-weight: 700;
                }

                .fallback h1 {
                    margin: 0 0 16px;
                    font-size: 44px;
                    line-height: 1;
                }

                .fallback p {
                    margin: 0 0 16px;
                    line-height: 1.7;
                    color: #5e544b;
                }

                .fallback__links {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 12px;
                    margin-top: 24px;
                }

                .fallback__links a {
                    display: inline-block;
                    padding: 12px 16px;
                    border-radius: 999px;
                    text-decoration: none;
                    color: #201914;
                    background: #fff;
                    border: 1px solid rgba(0, 0, 0, 0.08);
                }
            </style>
        @endif
    </head>
    <body>
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            <div id="app"></div>
        @else
            <main class="fallback">
                <section class="fallback__panel">
                    <p class="fallback__eyebrow">Contact AI API</p>
                    <h1>Vue frontend уже добавлен в проект.</h1>
                    <p>
                        Сейчас браузерная сборка ещё не подключена, потому что отсутствует Vite manifest.
                        Для полной работы страницы нужно выполнить frontend-сборку.
                    </p>
                    <p>
                        Backend API уже доступен, поэтому можно пользоваться документацией и системными endpoint.
                    </p>

                    <div class="fallback__links">
                        <a href="/api/documentation">Swagger</a>
                        <a href="/api/openapi.json">OpenAPI JSON</a>
                        <a href="/api/health">Health</a>
                        <a href="/api/metrics">Metrics</a>
                    </div>
                </section>
            </main>
        @endif
    </body>
</html>
