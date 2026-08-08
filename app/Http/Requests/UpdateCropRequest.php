<?php

namespace App\Http\Requests;

use App\Concerns\CropValidationRules;
use App\Models\Crop;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCropRequest extends FormRequest
{
    use CropValidationRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->targetCrop());
    }

    private function targetCrop(): Crop
    {
        $crop = $this->route('crop');

        if (! $crop instanceof Crop) {
            abort(404);
        }

        return $crop;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->cropRules($this->targetCrop());
    }
}
