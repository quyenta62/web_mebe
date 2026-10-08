<?php

namespace Tests\Feature;

use App\Models\CrawlRun;
use App\Models\FacebookGroup;
use App\Models\FacebookPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class GroupManagementTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'facebook_group_id' => '123456789',
            'url' => 'https://www.facebook.com/groups/123456789',
            'name' => 'Hội mẹ bỉm sữa',
            'is_active' => '1',
        ], $overrides);
    }

    public function test_index_lists_groups_with_post_counts(): void
    {
        $group = FacebookGroup::factory()->create(['name' => 'Hội mẹ bỉm sữa']);
        FacebookPost::factory()->count(3)->for($group, 'group')->create();

        $this->actingAsAdmin()->get('/groups')
            ->assertOk()
            ->assertSee('Hội mẹ bỉm sữa')
            ->assertSee($group->facebook_group_id)
            ->assertSeeInOrder(['Active', '3']);
    }

    public function test_index_is_paginated(): void
    {
        FacebookGroup::factory()->count(51)->create();

        $this->actingAsAdmin()->get('/groups')->assertOk()->assertSee('Hiển thị 1–50 / 51 groups');
        $this->get('/groups?page=2')->assertOk()->assertSee('Hiển thị 51–51 / 51 groups');
    }

    public function test_create_form_has_csrf_token(): void
    {
        $this->actingAsAdmin()->get('/groups/create')->assertOk()->assertSee('name="_token"', false);
    }

    public function test_admin_can_create_a_group_and_the_url_is_normalised(): void
    {
        $this->actingAsAdmin()
            ->post('/groups', $this->validInput(['url' => '  https://m.facebook.com/groups/123456789/posts/42/?ref=share ']))
            ->assertRedirect('/groups')
            ->assertSessionHas('status');

        $this->assertDatabaseHas('facebook_groups', [
            'facebook_group_id' => '123456789',
            'url' => 'https://www.facebook.com/groups/123456789/',
            'name' => 'Hội mẹ bỉm sữa',
            'is_active' => true,
        ]);
    }

    public function test_a_vanity_url_is_accepted(): void
    {
        $this->actingAsAdmin()
            ->post('/groups', $this->validInput(['url' => 'https://www.facebook.com/groups/mebimsuasaigon/', 'is_active' => '0']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('facebook_groups', [
            'url' => 'https://www.facebook.com/groups/mebimsuasaigon/',
            'is_active' => false,
        ]);
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: string}>
     */
    public static function invalidInputs(): array
    {
        return [
            'missing id' => [['facebook_group_id' => ''], 'facebook_group_id'],
            'non numeric id' => [['facebook_group_id' => '12345abc'], 'facebook_group_id'],
            'shell characters in id' => [['facebook_group_id' => '123456; rm -rf /'], 'facebook_group_id'],
            'too short id' => [['facebook_group_id' => '123'], 'facebook_group_id'],
            'missing url' => [['url' => ''], 'url'],
            'not facebook' => [['url' => 'https://evil.example/groups/123456789'], 'url'],
            'look-alike host' => [['url' => 'https://facebook.com.evil.example/groups/123456789'], 'url'],
            'javascript url' => [['url' => 'javascript:alert(1)//facebook.com/groups/123456789'], 'url'],
            'not a group url' => [['url' => 'https://www.facebook.com/profile.php?id=123456789'], 'url'],
            'url of another group' => [['url' => 'https://www.facebook.com/groups/987654321/'], 'url'],
            'name too long' => [['name' => str_repeat('a', 256)], 'name'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_rejected(array $overrides, string $field): void
    {
        $this->actingAsAdmin()
            ->from('/groups/create')
            ->post('/groups', $this->validInput($overrides))
            ->assertRedirect('/groups/create')
            ->assertSessionHasErrors($field);

        $this->assertSame(0, FacebookGroup::count());
    }

    public function test_duplicate_group_id_is_rejected(): void
    {
        FacebookGroup::factory()->create(['facebook_group_id' => '123456789']);

        $this->actingAsAdmin()->post('/groups', $this->validInput())->assertSessionHasErrors('facebook_group_id');
        $this->assertSame(1, FacebookGroup::count());
    }

    public function test_admin_can_update_a_group_but_not_its_facebook_id(): void
    {
        $group = FacebookGroup::factory()->create(['facebook_group_id' => '123456789', 'is_active' => true]);

        $this->actingAsAdmin()
            ->put("/groups/{$group->id}", [
                'facebook_group_id' => '999999999',
                'url' => 'https://www.facebook.com/groups/me-va-be/',
                'name' => 'Tên mới',
                'is_active' => '0',
            ])
            ->assertRedirect('/groups');

        $group->refresh();
        $this->assertSame('123456789', $group->facebook_group_id);
        $this->assertSame('https://www.facebook.com/groups/me-va-be/', $group->url);
        $this->assertSame('Tên mới', $group->name);
        $this->assertFalse($group->is_active);
    }

    public function test_update_validates_url_against_the_existing_group_id(): void
    {
        $group = FacebookGroup::factory()->create(['facebook_group_id' => '123456789']);

        $this->actingAsAdmin()
            ->put("/groups/{$group->id}", ['url' => 'https://www.facebook.com/groups/555555555/', 'is_active' => '1'])
            ->assertSessionHasErrors('url');
    }

    public function test_admin_can_disable_and_enable_a_group(): void
    {
        $group = FacebookGroup::factory()->create(['is_active' => true]);

        $this->actingAsAdmin()->patch("/groups/{$group->id}/toggle")->assertRedirect('/groups');
        $this->assertFalse($group->fresh()->is_active);

        $this->patch("/groups/{$group->id}/toggle");
        $this->assertTrue($group->fresh()->is_active);
    }

    public function test_deleting_a_group_keeps_posts_and_crawl_history(): void
    {
        $group = FacebookGroup::factory()->create(['name' => 'Group sẽ xoá']);
        FacebookPost::factory()->count(2)->for($group, 'group')->create();
        CrawlRun::factory()->for($group, 'group')->create();

        $this->actingAsAdmin()->delete("/groups/{$group->id}")->assertRedirect('/groups');

        $this->assertSoftDeleted($group);
        $this->assertSame(2, FacebookPost::count());
        $this->assertSame(1, CrawlRun::count());
        $this->get('/groups')->assertDontSee('Group sẽ xoá');
        $this->get("/groups/{$group->id}/edit")->assertNotFound();
    }

    public function test_re_adding_a_deleted_group_restores_it_with_its_posts(): void
    {
        $group = FacebookGroup::factory()->create(['facebook_group_id' => '123456789', 'name' => 'Cũ']);
        FacebookPost::factory()->count(2)->for($group, 'group')->create();
        $group->delete();

        $this->actingAsAdmin()->post('/groups', $this->validInput(['name' => 'Mới']))
            ->assertRedirect('/groups')
            ->assertSessionHas('status', fn ($message) => str_contains($message, 'khôi phục'));

        $this->assertSame(1, FacebookGroup::withTrashed()->count());
        $restored = FacebookGroup::first();
        $this->assertTrue($restored->is($group));
        $this->assertSame('Mới', $restored->name);
        $this->assertSame(2, $restored->posts()->count());
    }

    public function test_group_values_are_escaped_in_html(): void
    {
        FacebookGroup::factory()->create(['name' => '<script>alert("x")</script>']);

        $this->actingAsAdmin()->get('/groups')
            ->assertOk()
            ->assertDontSee('<script>alert("x")</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }
}
