<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * RefreshDatabase drops every table, so refuse to run against anything but the
     * local test database. Checked here, before any trait touches the database.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $connection = $app['config']->get('database.connections.'.$app['config']->get('database.default'));
        if (($connection['database'] ?? null) !== 'crawler_mebe_test' || ($connection['host'] ?? null) !== 'mysql') {
            throw new RuntimeException('Tests must run against the local crawler_mebe_test database (host "mysql"); check phpunit.xml.');
        }

        return $app;
    }
}
