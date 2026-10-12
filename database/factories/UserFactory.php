<?php

namespace Database\Factories;

use App\Domain\Identity\NationalCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'phone' => '09'.fake()->unique()->numerify('#########'),
            'national_code' => NationalCode::withCheckDigit(fake()->unique()->numerify('#########')),
            'password' => null,
            'password_is_initial' => false,
            'status' => 'active',
        ];
    }
}
