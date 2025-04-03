<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
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
            'user_id' => $this->user_id,
            'vehicle_id' => $this->vehicle_id,
            'parking_location_id' => $this->parking_location_id,
            'booking_type' => $this->booking_type,
            'status' => $this->status,
            'check_in_time' => $this->check_in_time,
            'check_out_time' => $this->check_out_time,
            'duration_hours' => $this->duration_hours,
            'amount' => $this->amount,
            'qr_code' => $this->qr_code,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => $this->when($this->relationLoaded('user'), new UserResource($this->user)),
            'vehicle' => $this->when($this->relationLoaded('vehicle'), new VehicleResource($this->vehicle)),
            'parking_location' => $this->when($this->relationLoaded('parkingLocation'), new ParkingLocationResource($this->parkingLocation)),
        ];
    }
}
