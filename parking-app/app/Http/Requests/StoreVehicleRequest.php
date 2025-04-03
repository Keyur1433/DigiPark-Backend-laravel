<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
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
        // Get the ID of the vehicle being updated (if applicable)
        $vehicleId = $this->route('vehicle') ? $this->route('vehicle')->id : null;

        return [
            'type' => ['required', 'string', 'in:2-wheeler,4-wheeler'],
            'number_plate' => [
                'required',
                'string',
                Rule::unique('vehicles')->ignore($vehicleId), // Ignore the current vehicle ID in update requests
            ],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Customize error messages.
     */
    public function messages(): array
    {
        return [
            'number_plate.unique' => 'The number plate must be unique. If you are updating, ensure it is not already in use by another vehicle.',
            'type.in' => 'The type must be either 2-wheeler or 4-wheeler.',
        ];
    }
}
