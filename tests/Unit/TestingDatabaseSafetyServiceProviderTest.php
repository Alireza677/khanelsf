<?php

namespace Tests\Unit;

use App\Providers\TestingDatabaseSafetyServiceProvider;
use RuntimeException;
use Tests\TestCase;

class TestingDatabaseSafetyServiceProviderTest extends TestCase
{
    public function test_testing_environment_rejects_a_non_testing_database(): void
    {
        config([
            'database.default' => 'unsafe',
            'database.connections.unsafe' => [
                'driver' => 'mysql',
                'database' => 'application',
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsafe testing database configuration');

        (new TestingDatabaseSafetyServiceProvider($this->app))->boot();
    }

    public function test_testing_environment_accepts_a_dedicated_testing_database(): void
    {
        config([
            'database.default' => 'safe',
            'database.connections.safe' => [
                'driver' => 'mysql',
                'database' => 'khanelsf_testing',
            ],
        ]);

        (new TestingDatabaseSafetyServiceProvider($this->app))->boot();

        $this->addToAssertionCount(1);
    }
}
