<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('admin-login:127.0.0.1');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->configureAdmin();

        $this->get('/groups')->assertRedirect('/login');
        $this->get('/')->assertRedirect('/login');
        $this->post('/groups', [])->assertRedirect('/login');
    }

    public function test_admin_can_log_in_with_env_credentials(): void
    {
        $this->configureAdmin();

        $this->post('/login', ['username' => 'admin', 'password' => 'secret-password'])
            ->assertRedirect('/groups')
            ->assertSessionHas('admin_username', 'admin');

        $this->get('/groups')->assertOk();
    }

    public function test_wrong_credentials_are_rejected(): void
    {
        $this->configureAdmin();

        $this->from('/login')->post('/login', ['username' => 'admin', 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('username')
            ->assertSessionMissing('admin_username');
    }

    public function test_login_is_impossible_while_credentials_are_not_configured(): void
    {
        $this->configureAdmin('', '');

        $this->get('/login')->assertOk()->assertSee('Chưa cấu hình tài khoản');
        $this->from('/login')->post('/login', ['username' => 'admin', 'password' => ''])
            ->assertSessionHasErrors('password');
        $this->post('/login', ['username' => 'x', 'password' => 'x'])->assertSessionMissing('admin_username');
        $this->withSession(['admin_username' => ''])->get('/groups')->assertRedirect('/login');
    }

    public function test_login_is_throttled_after_five_failures(): void
    {
        $this->configureAdmin();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => 'admin', 'password' => 'wrong']);
        }

        // Even the right password is refused while throttled.
        $this->post('/login', ['username' => 'admin', 'password' => 'secret-password'])
            ->assertSessionHasErrors(['username' => 'Đăng nhập sai quá nhiều lần. Thử lại sau 60 giây.'])
            ->assertSessionMissing('admin_username');
    }

    public function test_logout_ends_the_session(): void
    {
        $this->actingAsAdmin()->post('/logout')->assertRedirect('/login');

        $this->get('/groups')->assertRedirect('/login');
    }

    public function test_changing_the_admin_username_invalidates_existing_sessions(): void
    {
        $this->actingAsAdmin();
        config(['monitor.admin.username' => 'new-admin']);

        $this->get('/groups')->assertRedirect('/login');
    }
}
