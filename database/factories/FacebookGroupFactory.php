<?php

namespace Database\Factories;

use App\Models\FacebookGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FacebookGroup>
 */
class FacebookGroupFactory extends Factory
{
    public function definition(): array
    {
        $groupId = (string) fake()->unique()->numberBetween(100_000_000_000, 999_999_999_999_999);

        return [
            'facebook_group_id' => $groupId,
            'name' => 'Hội '.fake()->words(3, true),
            'url' => "https://www.facebook.com/groups/{$groupId}/",
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
