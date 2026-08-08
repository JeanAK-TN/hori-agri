<?php

namespace App\Http\Requests;

use App\Concerns\PlotValidationRules;
use App\Models\Plot;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePlotRequest extends FormRequest
{
    use PlotValidationRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->targetPlot());
    }

    private function targetPlot(): Plot
    {
        $plot = $this->route('plot');

        if (! $plot instanceof Plot) {
            abort(404);
        }

        return $plot;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->plotRules();
    }
}
