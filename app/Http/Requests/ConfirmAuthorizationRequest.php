<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmAuthorizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'authorization_confirmed' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'authorization_confirmed.accepted' => 'You must explicitly confirm that you are authorized to perform security testing against this target.',
        ];
    }
}
