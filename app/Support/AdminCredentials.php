<?php

namespace App\Support;

/**
 * The admin account lives in .env (ADMIN_USERNAME / ADMIN_PASSWORD), not in the database.
 */
class AdminCredentials
{
    public static function configured(): bool
    {
        return self::username() !== '' && self::password() !== '';
    }

    public static function username(): string
    {
        return (string) config('monitor.admin.username');
    }

    public static function check(string $username, string $password): bool
    {
        if (! self::configured()) {
            return false;
        }

        // Compare both values in constant time, without short-circuiting.
        $usernameMatches = hash_equals(self::username(), $username);
        $passwordMatches = hash_equals(self::password(), $password);

        return $usernameMatches && $passwordMatches;
    }

    private static function password(): string
    {
        return (string) config('monitor.admin.password');
    }
}
