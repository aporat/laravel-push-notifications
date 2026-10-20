<?php

declare(strict_types=1);

namespace Aporat\PushNotifications\Laravel\Tests;

use Aporat\PushNotifications\Laravel\Facades\Push;
use Aporat\PushNotifications\Laravel\PushManager;
use Illuminate\Support\Facades\Artisan;

final class ServiceProviderTest extends TestCase
{
    public function test_it_binds_the_manager_as_a_singleton(): void
    {
        self::assertTrue($this->app->bound('push-notifications'));

        $manager = $this->app->make(PushManager::class);

        self::assertInstanceOf(PushManager::class, $manager);
        self::assertSame($this->app->make('push-notifications'), $manager);
        self::assertSame($manager, Push::getFacadeRoot());
    }

    public function test_it_merges_the_default_config(): void
    {
        self::assertSame('production', config('push-notifications.apns.environment'));
        self::assertTrue(config('push-notifications.token_cache.enabled'));
        self::assertSame(50, config('push-notifications.http.concurrency'));
    }

    public function test_it_registers_the_test_command(): void
    {
        self::assertArrayHasKey('push:test', Artisan::all());
    }
}
