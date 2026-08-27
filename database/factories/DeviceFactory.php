<?php

namespace Database\Factories;

use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition(): array
    {
        $token = fake()->sha256().fake()->sha256();

        return [
            'token' => $token,
            'token_hash' => Device::hashFor($token),
            'platform' => fake()->randomElement(['android', 'ios']),
            'app_version' => '1.1.1+5',
            'locale' => 'fr-CD',
            'last_seen_at' => now(),
        ];
    }
}
