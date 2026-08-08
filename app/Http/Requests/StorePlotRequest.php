<?php

namespace App\Http\Requests;

use App\Concerns\PlotValidationRules;
use App\Models\Plot;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePlotRequest extends FormRequest
{
    use PlotValidationRules;

    public function authorize(): bool
    {
        return $this->user()->can('create', Plot::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->plotRules();
    }
}
