<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $metricsPath = storage_path(
            'framework/testing/'.Str::slug(static::class.'-'.$this->name()).'-metrics.json'
        );

        if (file_exists($metricsPath)) {
            unlink($metricsPath);
        }

        config([
            'metrics.path' => $metricsPath,
        ]);
    }
}
