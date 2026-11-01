<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Hard guard: RefreshDatabase wipes the configured database.
     *
     * When config is cached (e.g. after `php artisan optimize`), the
     * DB_DATABASE override from phpunit.xml is ignored and tests would run
     * against the real "sbu" development database. Refuse to start instead.
     */
    protected function beforeRefreshingDatabase()
    {
        $database = config('database.connections.mysql.database');

        if ($database !== 'sbu_test') {
            throw new RuntimeException(
                "Refusing to run destructive tests against '{$database}'. Expected 'sbu_test'. "
                .'Run `php artisan optimize:clear` if the configuration was cached.'
            );
        }
    }
}
