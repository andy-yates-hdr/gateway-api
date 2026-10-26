<?php

namespace App\Services\Compare;

interface ExpressionEvaluator
{
    /**
     * Evaluate one expression against a document. An expression that selects
     * nothing returns null, so gaps in the data are not errors.
     *
     * @throws ExpressionException when the expression is invalid or fails to evaluate
     */
    public function evaluate(string $expression, array $document): mixed;
}
