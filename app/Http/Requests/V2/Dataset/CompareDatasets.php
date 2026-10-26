<?php

namespace App\Http\Requests\V2\Dataset;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class CompareDatasets extends BaseFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        $maxDatasets = (int) config('gateway.compare_max_datasets');
        $maxPaths = (int) config('gateway.compare_max_paths');
        $maxLength = (int) config('gateway.compare_max_path_length');

        return [
            'datasets' => ['required', 'array', 'min:2', "max:{$maxDatasets}"],
            'datasets.*' => ['integer', 'distinct'],
            'primary_dataset' => ['nullable', 'integer', Rule::in((array) $this->input('datasets', []))],
            'schema' => ['nullable', 'string', 'max:50', 'required_with:version'],
            'version' => ['nullable', 'string', 'max:50', 'required_with:schema'],
            'paths' => ['required', 'array', 'min:1', "max:{$maxPaths}"],
            'paths.*' => ['string', 'filled', "max:{$maxLength}"],
        ];
    }
}
