<?php

namespace App\Http\Requests;

use App\Concerns\CropValidationRules;
use App\Models\Variety;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVarietyRequest extends FormRequest
{
    use CropValidationRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->targetVariety());
    }

    private function targetVariety(): Variety
    {
        $variety = $this->route('variety');

        if (! $variety instanceof Variety) {
            abort(404);
        }

        return $variety;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->varietyRules($this->targetVariety());
    }
}
