<?php

declare(strict_types=1);

return [
    'openapi' => '3.1.0',
    'info' => [
        'title' => 'Contact AI API',
        'version' => '1.0.0',
        'description' => 'Backend API для формы обратной связи с AI-анализом, письмами и метриками.',
    ],
    'paths' => [
        '/api/contact' => [
            'post' => [
                'summary' => 'Create contact request',
                'description' => 'Принимает обращение пользователя, анализирует комментарий через AI, отправляет письма и возвращает JSON-ответ.',
                'tags' => ['Contact'],
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                '$ref' => '#/components/schemas/ContactRequest',
                            ],
                            'example' => [
                                'name' => 'Никита',
                                'phone' => '+79999999999',
                                'email' => 'nikita@example.com',
                                'comment' => 'Хочу обсудить разработку интернет-магазина',
                            ],
                        ],
                    ],
                ],
                'responses' => [
                    '201' => [
                        'description' => 'Contact request created',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/ContactSuccessResponse',
                                ],
                            ],
                        ],
                    ],
                    '422' => [
                        'description' => 'Validation failed',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/ValidationErrorResponse',
                                ],
                            ],
                        ],
                    ],
                    '429' => [
                        'description' => 'Rate limit exceeded',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/BasicErrorResponse',
                                ],
                            ],
                        ],
                    ],
                    '500' => [
                        'description' => 'Unexpected server error',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/BasicErrorResponse',
                                ],
                            ],
                        ],
                    ],
                    '503' => [
                        'description' => 'Mail service unavailable',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/BasicErrorResponse',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        '/api/health' => [
            'get' => [
                'summary' => 'Application health status',
                'description' => 'Показывает доступность приложения, AI-конфигурации и почтовой конфигурации без выполнения реальных внешних запросов.',
                'tags' => ['System'],
                'responses' => [
                    '200' => [
                        'description' => 'Health status',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/HealthResponse',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        '/api/metrics' => [
            'get' => [
                'summary' => 'Application metrics',
                'description' => 'Возвращает текущие счётчики запросов, успешных обработок, сбоев и AI fallback.',
                'tags' => ['System'],
                'responses' => [
                    '200' => [
                        'description' => 'Metrics response',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/MetricsResponse',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'components' => [
        'schemas' => [
            'ContactRequest' => [
                'type' => 'object',
                'required' => ['name', 'phone', 'email', 'comment'],
                'properties' => [
                    'name' => [
                        'type' => 'string',
                        'minLength' => 2,
                        'maxLength' => 100,
                    ],
                    'phone' => [
                        'type' => 'string',
                        'minLength' => 7,
                        'maxLength' => 20,
                        'pattern' => '^\+?[0-9\s\-()]+$',
                    ],
                    'email' => [
                        'type' => 'string',
                        'format' => 'email',
                        'maxLength' => 255,
                    ],
                    'comment' => [
                        'type' => 'string',
                        'minLength' => 10,
                        'maxLength' => 3000,
                    ],
                ],
            ],
            'ContactSuccessResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => [
                        'type' => 'boolean',
                        'example' => true,
                    ],
                    'message' => [
                        'type' => 'string',
                        'example' => 'Обращение успешно отправлено.',
                    ],
                    'data' => [
                        'type' => 'object',
                        'properties' => [
                            'category' => [
                                'type' => 'string',
                                'enum' => ['project_request', 'support', 'cooperation', 'question', 'other'],
                            ],
                            'sentiment' => [
                                'type' => 'string',
                                'enum' => ['positive', 'neutral', 'negative'],
                            ],
                            'priority' => [
                                'type' => 'string',
                                'enum' => ['low', 'medium', 'high'],
                            ],
                            'processed_by_ai' => [
                                'type' => 'boolean',
                            ],
                        ],
                    ],
                ],
            ],
            'ValidationErrorResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => [
                        'type' => 'boolean',
                        'example' => false,
                    ],
                    'message' => [
                        'type' => 'string',
                        'example' => 'Данные не прошли проверку.',
                    ],
                    'errors' => [
                        'type' => 'object',
                        'additionalProperties' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
            'BasicErrorResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => [
                        'type' => 'boolean',
                        'example' => false,
                    ],
                    'message' => [
                        'type' => 'string',
                        'example' => 'Сервис временно недоступен. Попробуйте позже.',
                    ],
                ],
            ],
            'HealthResponse' => [
                'type' => 'object',
                'properties' => [
                    'status' => [
                        'type' => 'string',
                        'example' => 'ok',
                    ],
                    'timestamp' => [
                        'type' => 'string',
                        'format' => 'date-time',
                    ],
                    'services' => [
                        'type' => 'object',
                        'properties' => [
                            'application' => [
                                'type' => 'string',
                                'example' => 'available',
                            ],
                            'ai' => [
                                'type' => 'string',
                                'example' => 'configured',
                            ],
                            'mail' => [
                                'type' => 'string',
                                'example' => 'configured',
                            ],
                        ],
                    ],
                ],
            ],
            'MetricsResponse' => [
                'type' => 'object',
                'properties' => [
                    'total_requests' => [
                        'type' => 'integer',
                        'example' => 10,
                    ],
                    'successful_requests' => [
                        'type' => 'integer',
                        'example' => 8,
                    ],
                    'failed_requests' => [
                        'type' => 'integer',
                        'example' => 2,
                    ],
                    'ai_fallbacks' => [
                        'type' => 'integer',
                        'example' => 1,
                    ],
                ],
            ],
        ],
    ],
];
