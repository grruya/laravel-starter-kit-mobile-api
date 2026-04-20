<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OneTimePasswordPurpose;
use App\Models\OneTimePassword;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<OneTimePassword>
 */
final class OneTimePasswordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'purpose' => fake()->randomElement(OneTimePasswordPurpose::cases()),
            'code_hash' => Hash::make('123456'),
            'device_id_hash' => hash('sha256', fake()->uuid()),
            'attempts' => 0,
            'sent_at' => now(),
            'expires_at' => now()->addMinutes(10),
        ];
    }
}
