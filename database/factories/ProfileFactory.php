<?php

namespace Database\Factories;

use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'platform' => 'instagram',
            'followers_count' => fake()->numberBetween(100, 100000),
            'following_count' => fake()->numberBetween(50, 2000),
            'posts_count' => fake()->numberBetween(10, 500),
            'bio' => fake()->sentence(),
            'profile_picture_url' => fake()->imageUrl(),
            'status' => 'fetched',
            'error_message' => null,
            'last_refreshed_at' => now(),
        ];
    }
}
