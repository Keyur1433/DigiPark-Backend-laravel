<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'contact_number' => ['required', 'string', 'unique:users', 'regex:/^[0-9]{10}$/'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'state' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', 'in:admin,owner,user'],
        ];
    }

    /**
     * Get the custom error messages for validation.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'first_name.string' => 'First name must be a valid string.',
            'first_name.max' => 'First name cannot exceed 255 characters.',

            'last_name.required' => 'Last name is required.',
            'last_name.string' => 'Last name must be a valid string.',
            'last_name.max' => 'Last name cannot exceed 255 characters.',

            'email.required' => 'Email is required.',
            'email.string' => 'Email must be a valid string.',
            'email.email' => 'Please provide a valid email address.',
            'email.max' => 'Email cannot exceed 255 characters.',
            'email.unique' => 'This email is already taken.',

            'contact_number.required' => 'Contact number is required.',
            'contact_number.string' => 'Contact number must be a valid string.',
            'contact_number.unique' => 'This contact number is already registered.',
            'contact_number.regex' => 'Contact number must be a 10-digit number.',

            'password.required' => 'Password is required.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password.password' => 'Password must meet the default security requirements.',

            'state.required' => 'State is required.',
            'state.string' => 'State must be a valid string.',
            'state.max' => 'State cannot exceed 255 characters.',

            'city.required' => 'City is required.',
            'city.string' => 'City must be a valid string.',
            'city.max' => 'City cannot exceed 255 characters.',

            'country.required' => 'Country is required.',
            'country.string' => 'Country must be a valid string.',
            'country.max' => 'Country cannot exceed 255 characters.',

            'role.required' => 'Role is required.',
            'role.string' => 'Role must be a valid string.',
            'role.in' => 'Role must be one of the following: admin, owner, or user.',
        ];
    }
}
