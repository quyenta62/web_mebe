<?php

namespace Database\Factories;

use App\Enums\CrawlRunStatus;
use App\Models\CrawlRun;
use App\Models\FacebookGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CrawlRun>
 */
class CrawlRunFactory extends Factory
{
    public function definition(): array
    {
        return [
            'facebook_group_id' => FacebookGroup::factory(),
            'status' => CrawlRunStatus::Pending,
        ];
    }

    public function succeeded(int $found = 30, int $created = 10): static
    {
        return $this->state(fn () => [
            'status' => CrawlRunStatus::Success,
            'posts_found' => $found,
            'posts_created' => $created,
            'started_at' => now()->subMinutes(2),
            'finished_at' => now(),
        ]);
    }

    public function failed(string $error = 'LOGIN_REQUIRED: Facebook session is expired'): static
    {
        return $this->state(fn () => [
            'status' => CrawlRunStatus::Failed,
            'error_message' => $error,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
    }
}
