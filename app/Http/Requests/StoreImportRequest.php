<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreImportRequest extends FormRequest
{
    /**
     * The route already restricts the screen to the roles of SPEC
     * section 11; the deposit itself carries no further condition.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'fichier' => ['required', 'file', 'mimetypes:text/plain,text/csv,application/csv', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fichier.required' => 'Choisissez un fichier à déposer.',
            'fichier.mimetypes' => 'Le fichier doit être au format CSV.',
            'fichier.max' => 'Le fichier ne doit pas dépasser 4 Mo.',
        ];
    }
}
