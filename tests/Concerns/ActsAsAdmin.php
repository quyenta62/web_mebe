<?php

namespace Tests\Concerns;

trait ActsAsAdmin
{
    protected function configureAdmin(string $username = 'admin', string $password = 'secret-password'): void
    {
        config(['monitor.admin.username' => $username, 'monitor.admin.password' => $password]);
    }

    protected function actingAsAdmin(): static
    {
        $this->configureAdmin();

        return $this->withSession(['admin_username' => 'admin']);
    }
}
