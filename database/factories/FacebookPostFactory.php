<?php

namespace Database\Factories;

use App\Models\FacebookGroup;
use App\Models\FacebookPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FacebookPost>
 */
class FacebookPostFactory extends Factory
{
    public function definition(): array
    {
        $postId = (string) fake()->unique()->numberBetween(1_000_000_000_000, 9_999_999_999_999_999);

        return [
            'facebook_group_id' => FacebookGroup::factory(),
            'facebook_post_id' => $postId,
            'author_name' => fake()->name(),
            'content' => fake()->paragraph(),
            'post_url' => fn (array $attributes) => sprintf(
                'https://www.facebook.com/groups/%s/posts/%s/',
                FacebookGroup::find($attributes['facebook_group_id'])?->facebook_group_id,
                $postId,
            ),
            'posted_at' => fake()->dateTimeBetween('-30 days'),
        ];
    }
}
