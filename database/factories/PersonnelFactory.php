<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Personnel\PersonnelStatus;
use App\Models\Personnel\Personnel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Personnel>
 */
class PersonnelFactory extends Factory
{
    protected $model = Personnel::class;

    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->name();

        return [
            'full_name' => $name,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'locale' => 'tr',
            'timezone' => 'Europe/Istanbul',
            'status' => PersonnelStatus::Active,
        ];
    }

    public function invited(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PersonnelStatus::Invited,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }
}
