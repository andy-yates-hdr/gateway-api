<?php

namespace App\Services\Compare;

/**
 * Resolves Laravel data_get path strings: dot notation, `*` wildcards, and
 * {first}/{last}. A path that selects nothing returns null.
 */
class DataGetExpressionEvaluator implements ExpressionEvaluator
{
    public function evaluate(string $expression, array $document): mixed
    {
        return data_get($document, $expression);
    }
}
