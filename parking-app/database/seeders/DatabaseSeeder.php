<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;
use Carbon\Carbon;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();

        $users = [];
        $roles = ['admin', 'owner', 'user'];

        for ($i = 0; $i < 30; $i++) {
            $users[] = [
                'first_name' => $faker->firstName,
                'last_name' => $faker->lastName,
                'email' => $faker->unique()->safeEmail,
                'contact_number' => $faker->unique()->phoneNumber,
                'password' => Hash::make('password123'),
                'state' => $faker->state,
                'city' => $faker->city,
                'country' => $faker->country,
                'role' => $roles[array_rand($roles)],
                'email_verified_at' => $faker->boolean(70) ? now() : null,
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        // Ensure at least one admin and one owner
        $users[0]['role'] = 'admin';
        $users[1]['role'] = 'owner';

        DB::table('users')->insert($users);
    }
}

class OtpVerificationSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();
        $users = DB::table('users')->pluck('id');

        $otpVerifications = [];
        for ($i = 0; $i < 25; $i++) {
            $otpVerifications[] = [
                'user_id' => $users->random(),
                'otp' => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT),
                'type' => $faker->randomElement(['registration', 'password_reset']),
                'expires_at' => now()->addMinutes(15),
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        DB::table('otp_verifications')->insert($otpVerifications);
    }
}

class VehicleSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();
        $users = DB::table('users')->pluck('id');

        $vehicles = [];
        $vehicleTypes = ['2-wheeler', '4-wheeler'];
        $brands = [
            '2-wheeler' => ['Honda', 'Yamaha', 'Suzuki', 'Kawasaki', 'TVS'],
            '4-wheeler' => ['Toyota', 'Honda', 'Ford', 'Chevrolet', 'Nissan']
        ];

        for ($i = 0; $i < 30; $i++) {
            $type = $faker->randomElement($vehicleTypes);
            $vehicles[] = [
                'user_id' => $users->random(),
                'type' => $type,
                'number_plate' => strtoupper(substr($faker->word, 0, 3) . random_int(100, 999)),
                'brand' => $faker->randomElement($brands[$type]),
                'model' => $faker->word,
                'color' => $faker->colorName,
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        DB::table('vehicles')->insert($vehicles);
    }
}

class ParkingLocationSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();
        $owners = DB::table('users')->where('role', 'owner')->pluck('id');

        $parkingLocations = [];
        for ($i = 0; $i < 25; $i++) {
            $parkingLocations[] = [
                'owner_id' => $owners->random(),
                'name' => $faker->company . ' Parking',
                'address' => $faker->streetAddress,
                'city' => $faker->city,
                'state' => $faker->state,
                'country' => $faker->country,
                'zip_code' => $faker->postcode,
                'latitude' => $faker->latitude,
                'longitude' => $faker->longitude,
                'two_wheeler_capacity' => random_int(20, 100),
                'four_wheeler_capacity' => random_int(50, 200),
                'two_wheeler_hourly_rate' => $faker->randomFloat(2, 1, 5),
                'four_wheeler_hourly_rate' => $faker->randomFloat(2, 3, 10),
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        DB::table('parking_locations')->insert($parkingLocations);
    }
}

class ParkingSlotAvailabilitySeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();
        $parkingLocations = DB::table('parking_locations')->pluck('id');

        $slotAvailabilities = [];
        foreach ($parkingLocations as $locationId) {
            $twoWheelerTotal = random_int(20, 100);
            $fourWheelerTotal = random_int(50, 200);

            $slotAvailabilities[] = [
                'parking_location_id' => $locationId,
                'vehicle_type' => '2-wheeler',
                'available_slots' => random_int(0, $twoWheelerTotal),
                'total_slots' => $twoWheelerTotal,
                'created_at' => now(),
                'updated_at' => now()
            ];

            $slotAvailabilities[] = [
                'parking_location_id' => $locationId,
                'vehicle_type' => '4-wheeler',
                'available_slots' => random_int(0, $fourWheelerTotal),
                'total_slots' => $fourWheelerTotal,
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        DB::table('parking_slot_availabilities')->insert($slotAvailabilities);
    }
}

class ParkingBookingSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();
        $users = DB::table('users')->pluck('id');
        $vehicles = DB::table('vehicles')->pluck('id');
        $parkingLocations = DB::table('parking_locations')->pluck('id');

        $parkingBookings = [];
        for ($i = 0; $i < 30; $i++) {
            $checkInTime = $faker->dateTimeBetween('now', '+1 month');
            $duration = random_int(1, 12);
            $checkOutTime = (clone $checkInTime)->modify("+{$duration} hours");

            $parkingBookings[] = [
                'user_id' => $users->random(),
                'vehicle_id' => $vehicles->random(),
                'parking_location_id' => $parkingLocations->random(),
                'booking_type' => $faker->randomElement(['check_in', 'advance']),
                'status' => $faker->randomElement(['upcoming', 'checked_in', 'completed', 'cancelled']),
                'check_in_time' => $checkInTime,
                'check_out_time' => $checkOutTime,
                'duration_hours' => $duration,
                'amount' => $faker->randomFloat(2, 5, 50),
                'qr_code' => 'QR_' . strtoupper(substr(md5(uniqid()), 0, 10)),
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        DB::table('parking_bookings')->insert($parkingBookings);
    }
}

class ParkingTimeSlotSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();
        $parkingLocations = DB::table('parking_locations')->pluck('id');

        $parkingTimeSlots = [];
        foreach ($parkingLocations as $locationId) {
            for ($j = 0; $j < 5; $j++) {
                $date = $faker->dateTimeBetween('now', '+1 month');
                $startTime = $faker->time('H:i:s');
                $endTime = (new \DateTime($startTime))->modify('+4 hours')->format('H:i:s');

                $parkingTimeSlots[] = [
                    'parking_location_id' => $locationId,
                    'vehicle_type' => '2-wheeler',
                    'date' => $date->format('Y-m-d'),
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'available_slots' => random_int(10, 50),
                    'created_at' => now(),
                    'updated_at' => now()
                ];

                $parkingTimeSlots[] = [
                    'parking_location_id' => $locationId,
                    'vehicle_type' => '4-wheeler',
                    'date' => $date->format('Y-m-d'),
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'available_slots' => random_int(20, 100),
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
        }

        DB::table('parking_time_slots')->insert($parkingTimeSlots);
    }
}

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Truncate tables with cascade to remove all related records
        DB::statement('TRUNCATE TABLE parking_time_slots CASCADE');
        DB::statement('TRUNCATE TABLE parking_bookings CASCADE');
        DB::statement('TRUNCATE TABLE parking_slot_availabilities CASCADE');
        DB::statement('TRUNCATE TABLE parking_locations CASCADE');
        DB::statement('TRUNCATE TABLE vehicles CASCADE');
        DB::statement('TRUNCATE TABLE otp_verifications CASCADE');
        DB::statement('TRUNCATE TABLE users CASCADE');

        // Reset sequences for auto-incrementing primary keys
        DB::statement('ALTER SEQUENCE users_id_seq RESTART WITH 1');
        DB::statement('ALTER SEQUENCE otp_verifications_id_seq RESTART WITH 1');
        DB::statement('ALTER SEQUENCE vehicles_id_seq RESTART WITH 1');
        DB::statement('ALTER SEQUENCE parking_locations_id_seq RESTART WITH 1');
        DB::statement('ALTER SEQUENCE parking_slot_availabilities_id_seq RESTART WITH 1');
        DB::statement('ALTER SEQUENCE parking_bookings_id_seq RESTART WITH 1');
        DB::statement('ALTER SEQUENCE parking_time_slots_id_seq RESTART WITH 1');

        // Run seeders
        $this->call([
            UserSeeder::class,
            OtpVerificationSeeder::class,
            VehicleSeeder::class,
            ParkingLocationSeeder::class,
            ParkingSlotAvailabilitySeeder::class,
            ParkingBookingSeeder::class,
            ParkingTimeSlotSeeder::class
        ]);
    }
}
