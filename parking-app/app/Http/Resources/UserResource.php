<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'contact_number' => $this->contact_number,
            'state' => $this->state,
            'city' => $this->city,
            'country' => $this->country,
            'role' => $this->role,
            'is_verified' => (bool) $this->is_verified,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'vehicles' => $this->when($this->relationLoaded('vehicles'), VehicleResource::collection($this->vehicles)),
            'bookings' => $this->when($this->relationLoaded('bookings'), BookingResource::collection($this->bookings)),
        ];
    }
}
