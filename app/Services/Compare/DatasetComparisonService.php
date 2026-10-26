<?php

namespace App\Services\Compare;

use App\Context\PartnerContext;
use App\Models\Dataset;
use App\Services\DatasetService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Retrieves each dataset as the same normalised document a show request returns
 * (optionally translated to a requested schema), then hands them to the matrix
 * builder. Documents are shaped { data: <dataset resource> } so expressions read
 * as data.versions.0.metadata.....
 */
class DatasetComparisonService
{
    public function __construct(
        private readonly DatasetService $datasetService,
        private readonly PartnerContext $partnerContext,
        private readonly ComparisonMatrixBuilder $builder,
    ) {
    }

    /**
     * @param  int[]  $datasetIds
     * @param  string[]  $expressions
     * @return array{matrix: array, comparisons: array}
     *
     * @throws ModelNotFoundException when any dataset is missing or not visible to the partner
     * @throws \InvalidArgumentException|\RuntimeException from schema translation
     * @throws ExpressionException
     */
    public function compare(
        array $datasetIds,
        array $expressions,
        ?int $primaryId = null,
        ?string $schemaModel = null,
        ?string $schemaVersion = null,
    ): array {
        $documents = [];

        foreach ($datasetIds as $id) {
            $documents[$id] = $this->document((int) $id, $schemaModel, $schemaVersion);
        }

        return $this->builder->build($documents, $expressions, $primaryId);
    }

    private function document(int $id, ?string $schemaModel, ?string $schemaVersion): array
    {
        $dataset = $this->datasetService->findActive($id, $this->partnerContext->getPartner());

        // Same outcome whether the dataset is absent or hidden, so ids cannot be probed.
        if (!$dataset) {
            throw new ModelNotFoundException("Dataset {$id} not found");
        }

        $dataset = $this->datasetService->prepareForShow($dataset, $schemaModel, $schemaVersion);

        $resource = $this->partnerContext->resourceFor(Dataset::class)::make($dataset)->resolve(request());

        // Round trip so nested resources and models are plain arrays.
        return ['data' => json_decode(json_encode($resource), true)];
    }
}
