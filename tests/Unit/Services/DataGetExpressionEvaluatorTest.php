<?php

namespace Tests\Unit\Services;

use App\Services\Compare\DataGetExpressionEvaluator;
use PHPUnit\Framework\TestCase;

class DataGetExpressionEvaluatorTest extends TestCase
{
    private array $doc = ['data' => ['versions' => [['title' => 'A', 'tags' => [['n' => 'x'], ['n' => 'y']]]]]];

    public function test_dot_paths_resolve(): void
    {
        $e = new DataGetExpressionEvaluator();

        $this->assertSame('A', $e->evaluate('data.versions.0.title', $this->doc));
    }

    public function test_wildcards_and_gaps(): void
    {
        $e = new DataGetExpressionEvaluator();

        $this->assertSame(['x', 'y'], $e->evaluate('data.versions.0.tags.*.n', $this->doc));
        $this->assertNull($e->evaluate('data.versions.0.missing', $this->doc));
    }
}
