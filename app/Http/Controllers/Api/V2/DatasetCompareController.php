<?php

namespace App\Http\Controllers\Api\V2;

use App\Context\OutputSchemaContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\V2\Dataset\CompareDatasets;
use App\Services\Compare\DatasetComparisonService;
use App\Services\Compare\ExpressionException;
use Auditor;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class DatasetCompareController extends Controller
{
    public function __construct(
        private readonly DatasetComparisonService $comparisonService,
        private readonly OutputSchemaContext $outputSchemaContext,
    ) {
    }

    /**
     * @OA\Post(
     *    path="/api/v2/datasets/compare",
     *    operationId="compare_datasets_v2",
     *    tags={"Datasets"},
     *    summary="DatasetCompareController@compare",
     *    description="Resolve data_get path strings against each dataset's normalised document and return the results as a matrix",
     *    @OA\RequestBody(
     *       required=true,
     *       @OA\MediaType(
     *          mediaType="application/json",
     *          @OA\Schema(
     *             @OA\Property(property="datasets", type="array", @OA\Items(type="integer"), example={100, 101}),
     *             @OA\Property(property="primary_dataset", type="integer", example=100),
     *             @OA\Property(property="schema", type="string", example="HDRUK"),
     *             @OA\Property(property="version", type="string", example="4.0.0"),
     *             @OA\Property(property="paths", type="array", @OA\Items(type="string"), example={"data.versions.0.metadata.metadata.summary.title"})
     *          )
     *       )
     *    ),
     *    @OA\Response(response=200, description="Success response",
     *       @OA\JsonContent(
     *          @OA\Property(property="message", type="string", example="success"),
     *          @OA\Property(property="data", type="object",
     *             @OA\Property(property="matrix", type="array", @OA\Items(type="array", @OA\Items())),
     *             @OA\Property(property="comparisons", type="array", @OA\Items(type="array", @OA\Items(type="boolean")))
     *          )
     *       )
     *    ),
     *    @OA\Response(response=400, description="Invalid request or expression"),
     *    @OA\Response(response=404, description="A dataset was not found")
     * )
     */
    public function compare(CompareDatasets $request): JsonResponse
    {
        try {
            $result = $this->comparisonService->compare(
                datasetIds: array_map('intval', $request->input('datasets')),
                expressions: $request->input('paths'),
                primaryId: $request->filled('primary_dataset') ? $request->integer('primary_dataset') : null,
                // Same default as show, so compare sees what show returns.
                schemaModel: $request->input('schema') ?? $this->outputSchemaContext->schemaModel(),
                schemaVersion: $request->input('version') ?? $this->outputSchemaContext->schemaVersion(),
            );

            Auditor::log([
                'action_type' => 'POST',
                'action_name' => class_basename($this) . '@' . __FUNCTION__,
                'description' => 'Dataset compare ' . implode(',', $request->input('datasets')),
            ]);

            return response()->json(['message' => 'success', 'data' => $result]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Dataset not found'], 404);
        } catch (ExpressionException $e) {
            return response()->json(['message' => 'invalid path', 'details' => $e->getMessage()], 400);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => 'failed to translate', 'details' => $e->getMessage()], 400);
        } catch (Exception $e) {
            Auditor::log([
                'action_type' => 'EXCEPTION',
                'action_name' => class_basename($this) . '@' . __FUNCTION__,
                'description' => $e->getMessage(),
            ]);

            throw new Exception($e->getMessage());
        }
    }
}
