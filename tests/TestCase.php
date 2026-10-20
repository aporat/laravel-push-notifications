<?php

declare(strict_types=1);

namespace Aporat\PushNotifications\Laravel\Tests;

use Aporat\PushNotifications\Laravel\PushNotificationsServiceProvider;
use Illuminate\Foundation\Application;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [PushNotificationsServiceProvider::class];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('push-notifications.teams.acme', [
            'team_id' => 'TEAM123456',
            'key_id' => 'KEY1234567',
            'private_key' => Keys::ec(),
        ]);
        $app['config']->set('push-notifications.apps', [
            'ios' => ['driver' => 'apns', 'bundle_id' => 'com.acme.app', 'team' => 'acme'],
            'ios-sandbox' => ['driver' => 'apns', 'bundle_id' => 'com.acme.app', 'team' => 'acme', 'environment' => 'sandbox'],
            'watch' => ['driver' => 'apns', 'bundle_id' => 'com.acme.watch', 'team' => 'acme'],
            'android' => ['driver' => 'fcm', 'service_account' => Keys::serviceAccountJson()],
            'broken' => ['driver' => 'smoke-signals'],
        ]);
    }
}
