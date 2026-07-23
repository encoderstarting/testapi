<?php

declare(strict_types=1);

namespace App\DTO;

use InvalidArgumentException;

final readonly class AiAnalysisResult
{
    private const ALLOWED_CATEGORIES = [
        'project_request',
        'support',
        'cooperation',
        'question',
        'other',
    ];

    private const ALLOWED_SENTIMENTS = [
        'positive',
        'neutral',
        'negative',
    ];

    private const ALLOWED_PRIORITIES = [
        'low',
        'medium',
        'high',
    ];

    public function __construct(
        public string $category,
        public string $sentiment,
        public string $priority,
        public string $summary,
        public bool $processedByAi,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, bool $processedByAi = true): self
    {
        foreach (['category', 'sentiment', 'priority', 'summary'] as $field) {
            if (! array_key_exists($field, $data)) {
                throw new InvalidArgumentException(sprintf('Missing required AI field: %s', $field));
            }
        }

        $category = self::normalizeString($data['category'], 'category');
        $sentiment = self::normalizeString($data['sentiment'], 'sentiment');
        $priority = self::normalizeString($data['priority'], 'priority');
        $summary = self::normalizeString($data['summary'], 'summary');

        if (! in_array($category, self::ALLOWED_CATEGORIES, true)) {
            throw new InvalidArgumentException('Invalid AI category.');
        }

        if (! in_array($sentiment, self::ALLOWED_SENTIMENTS, true)) {
            throw new InvalidArgumentException('Invalid AI sentiment.');
        }

        if (! in_array($priority, self::ALLOWED_PRIORITIES, true)) {
            throw new InvalidArgumentException('Invalid AI priority.');
        }

        if ($summary === '') {
            throw new InvalidArgumentException('AI summary cannot be empty.');
        }

        if (mb_strlen($summary) > 150) {
            throw new InvalidArgumentException('AI summary is too long.');
        }

        return new self(
            category: $category,
            sentiment: $sentiment,
            priority: $priority,
            summary: $summary,
            processedByAi: $processedByAi,
        );
    }

    public static function fallbackFromComment(string $comment): self
    {
        return new self(
            category: 'other',
            sentiment: 'neutral',
            priority: 'medium',
            summary: trim(mb_substr($comment, 0, 150)),
            processedByAi: false,
        );
    }

    /**
     * @return array{
     *     category: string,
     *     sentiment: string,
     *     priority: string,
     *     summary: string,
     *     processed_by_ai: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'category' => $this->category,
            'sentiment' => $this->sentiment,
            'priority' => $this->priority,
            'summary' => $this->summary,
            'processed_by_ai' => $this->processedByAi,
        ];
    }

    /**
     * @return array{
     *     category: string,
     *     sentiment: string,
     *     priority: string,
     *     processed_by_ai: bool
     * }
     */
    public function toResponseArray(): array
    {
        return [
            'category' => $this->category,
            'sentiment' => $this->sentiment,
            'priority' => $this->priority,
            'processed_by_ai' => $this->processedByAi,
        ];
    }

    private static function normalizeString(mixed $value, string $field): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException(sprintf('AI field "%s" must be a string.', $field));
        }

        return trim($value);
    }
}
