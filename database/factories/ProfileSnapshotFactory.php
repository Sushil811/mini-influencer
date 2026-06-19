<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\ProfileSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfileSnapshot>
 */
class ProfileSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory(),
            'followers_count' => fake()->numberBetween(100, 100000),
            'following_count' => fake()->numberBetween(50, 2000),
            'posts_count' => fake()->numberBetween(10, 500),
            'created_at' => now(),
        ];
    }
}
