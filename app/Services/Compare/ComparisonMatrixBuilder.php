<?php

namespace App\Services\Compare;

/**
 * Evaluates expressions over documents and lays the results out as matrices.
 *
 * Both matrices have one row per expression and one column per document, in the
 * order given. Values carry no context: the caller already knows what it asked for.
 *
 * Comparisons are only made against a primary document. A cell is true when the
 * value is identical to the primary's value for that expression (the primary
 * matches itself). A gap (null) never matches, even against another gap: missing
 * data is unknown, not equal. With no primary every cell is false.
 */
class ComparisonMatrixBuilder
{
    public function __construct(private readonly ExpressionEvaluator $evaluator)
    {
    }

    /**
     * @param  array<int|string, array>  $documents  keyed by dataset id, in column order
     * @param  string[]  $expressions
     * @return array{matrix: array<int, array<int, mixed>>, comparisons: array<int, array<int, bool>>}
     *
     * @throws ExpressionException naming the offending expression index
     */
    public function build(array $documents, array $expressions, int|string|null $primaryId = null): array
    {
        $matrix = [];
        $comparisons = [];

        foreach (array_values($expressions) as $index => $expression) {
            $row = [];
            foreach ($documents as $id => $document) {
                try {
                    $row[$id] = $this->evaluator->evaluate($expression, $document);
                } catch (ExpressionException $e) {
                    throw new ExpressionException("Expression {$index} failed: " . $e->getMessage(), 0, $e);
                }
            }

            $matrix[] = array_values($row);
            $comparisons[] = array_map(
                fn ($value) => $primaryId !== null && array_key_exists($primaryId, $row)
                    && $value !== null
                    && $this->identical($value, $row[$primaryId]),
                array_values($row),
            );
        }

        return ['matrix' => $matrix, 'comparisons' => $comparisons];
    }

    private function identical(mixed $a, mixed $b): bool
    {
        return json_encode($a) === json_encode($b);
    }
}
