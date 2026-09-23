<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'target_url' => ['required', 'url', 'starts_with:http://,https://'],
            'environment' => ['required', 'in:development,staging,production'],
            'included_paths' => ['nullable', 'string'],
            'excluded_paths' => ['nullable', 'string'],
            'auth_mode' => ['required', 'in:none,form,browser,token'],
            'login_url' => ['nullable', 'required_if:auth_mode,form,browser', 'url'],
            'username_field' => ['nullable', 'required_if:auth_mode,form', 'string', 'max:255'],
            'password_field' => ['nullable', 'required_if:auth_mode,form', 'string', 'max:255'],
            'username' => ['nullable', 'required_if:auth_mode,form,browser', 'string', 'max:255'],
            'password' => ['nullable', 'required_if:auth_mode,form,browser', 'string', 'max:255'],
            'token_name' => ['nullable', 'required_if:auth_mode,token', 'string', 'max:255'],
            'token_value' => ['nullable', 'required_if:auth_mode,token', 'string'],
            'login_button_selector' => ['nullable', 'string', 'max:255'],
            'logged_in_indicator' => ['nullable', 'string', 'max:255'],
            'logged_out_indicator' => ['nullable', 'string', 'max:255'],
            'authenticated_url' => ['nullable', 'url'],
        ];
    }
}
