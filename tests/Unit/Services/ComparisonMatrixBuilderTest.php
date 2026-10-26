<?php

namespace Tests\Unit\Services;

use App\Services\Compare\ComparisonMatrixBuilder;
use App\Services\Compare\ExpressionEvaluator;
use App\Services\Compare\ExpressionException;
use PHPUnit\Framework\TestCase;

class ComparisonMatrixBuilderTest extends TestCase
{
    private function builder(): ComparisonMatrixBuilder
    {
        // Dot-path stand-in for JSONata, so this tests layout and comparison only.
        return new ComparisonMatrixBuilder(new class implements ExpressionEvaluator {
            public function evaluate(string $expression, array $document): mixed
            {
                if ($expression === 'boom') {
                    throw new ExpressionException('bad');
                }

                return data_get($document, $expression);
            }
        });
    }

    private function documents(): array
    {
        return [
            100 => ['title' => 'A', 'size' => 10, 'tags' => ['x', 'y']],
            101 => ['title' => 'B', 'size' => 10, 'tags' => ['x', 'y']],
            102 => ['title' => 'C'],
        ];
    }

    public function test_matrix_has_a_row_per_expression_and_a_column_per_document(): void
    {
        $result = $this->builder()->build($this->documents(), ['title', 'size']);

        $this->assertSame([['A', 'B', 'C'], [10, 10, null]], $result['matrix']);
    }

    public function test_comparisons_are_against_the_primary_and_primary_matches_itself(): void
    {
        $result = $this->builder()->build($this->documents(), ['title', 'size', 'tags'], 100);

        $this->assertSame([
            [true, false, false],
            [true, true, false],
            [true, true, false],
        ], $result['comparisons']);
    }

    public function test_comparisons_are_all_false_without_a_primary(): void
    {
        $result = $this->builder()->build($this->documents(), ['title', 'size']);

        $this->assertSame([[false, false, false], [false, false, false]], $result['comparisons']);
    }

    public function test_a_gap_never_matches_not_even_another_gap(): void
    {
        $result = $this->builder()->build($this->documents(), ['size'], 102);

        $this->assertSame([[false, false, false]], $result['comparisons']);
    }

    public function test_a_gap_in_every_document_is_all_false(): void
    {
        $result = $this->builder()->build($this->documents(), ['missing'], 100);

        $this->assertSame([[false, false, false]], $result['comparisons']);
    }

    public function test_a_failing_expression_is_reported_by_index(): void
    {
        $this->expectException(ExpressionException::class);
        $this->expectExceptionMessage('Expression 1 failed');

        $this->builder()->build($this->documents(), ['title', 'boom']);
    }
}
