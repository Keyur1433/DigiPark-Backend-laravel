<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ParkingLocationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'owner_id' => $this->owner_id,
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'country' => $this->country,
            'zip_code' => $this->zip_code,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'two_wheeler_capacity' => $this->two_wheeler_capacity,
            'four_wheeler_capacity' => $this->four_wheeler_capacity,
            'two_wheeler_hourly_rate' => $this->two_wheeler_hourly_rate,
            'four_wheeler_hourly_rate' => $this->four_wheeler_hourly_rate,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'owner' => $this->when($this->relationLoaded('owner'), new UserResource($this->owner)),
            'slot_availabilities' => $this->when($this->relationLoaded('slotAvailabilities'), $this->slotAvailabilities->map(function ($availability) {
                return [
                    'vehicle_type' => $availability->vehicle_type,
                    'available_slots' => $availability->available_slots,
                    'total_slots' => $availability->total_slots,
                ];
            })),
        ];
    }
}
