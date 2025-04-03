<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'contact_number' => ['required', 'string', 'exists:users,contact_number'],
            'otp' => ['required', 'string', 'size:6'],
            'type' => ['required', 'string', 'in:registration,password_reset'],
        ];
    }
}
