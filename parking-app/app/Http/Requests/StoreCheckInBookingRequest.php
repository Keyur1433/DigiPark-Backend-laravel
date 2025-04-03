<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCheckInBookingRequest extends FormRequest
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
            'parking_location_id' => ['required', 'exists:parking_locations,id'],
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'duration_hours' => ['required', 'integer', 'min:1', 'max:9'],
        ];
    }
}
