<?php

declare(strict_types=1);

namespace Aporat\PushNotifications\Laravel\Tests;

use Aporat\PushNotifications\Apns\Client as ApnsClient;
use Aporat\PushNotifications\Apns\Environment;
use Aporat\PushNotifications\Exceptions\ConfigurationException;
use Aporat\PushNotifications\Fcm\Client as FcmClient;
use Aporat\PushNotifications\Laravel\Facades\Push;
use Aporat\PushNotifications\Laravel\PushManager;
use Aporat\PushNotifications\Laravel\Support\Psr6\LaravelCacheItemPool;
use Aporat\PushNotifications\Platform;

final class PushManagerTest extends TestCase
{
    private function manager(): PushManager
    {
        return $this->app->make(PushManager::class);
    }

    public function test_apns_app(): void
    {
        $sender = $this->manager()->app('ios');

        self::assertSame('ios', $sender->name);
        self::assertSame(Platform::Apns, $sender->platform);
        self::assertSame('com.acme.app', $sender->bundleId());
        self::assertInstanceOf(ApnsClient::class, $sender->client());
        self::assertSame(Environment::Production, $sender->client()->environment());
        self::assertSame($sender, $this->manager()->app('ios'));
    }

    public function test_apps_in_one_team_share_one_apns_client_per_environment(): void
    {
        $manager = $this->manager();

        self::assertSame($manager->app('ios')->client(), $manager->app('watch')->client());
        self::assertNotSame($manager->app('ios')->client(), $manager->app('ios-sandbox')->client());
        self::assertSame(Environment::Sandbox, $manager->app('ios-sandbox')->client()->environment());
        self::assertSame($manager->app('ios')->client(), $manager->apnsClient('acme', Environment::Production));
    }

    public function test_fcm_app(): void
    {
        $sender = $this->manager()->app('android');

        self::assertSame(Platform::Fcm, $sender->platform);
        self::assertNull($sender->bundleId());
        self::assertInstanceOf(FcmClient::class, $sender->client());
        self::assertSame('demo-project', $sender->client()->account()->projectId);
        self::assertSame($sender->client(), $this->manager()->fcmClient('android'));
    }

    public function test_fcm_service_account_from_a_file(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'sa');
        self::assertNotFalse($path);
        file_put_contents($path, Keys::serviceAccountJson('file-project'));
        config()->set('push-notifications.apps.android.service_account', $path);

        try {
            self::assertSame('file-project', $this->manager()->fcmClient('android')->account()->projectId);
        } finally {
            unlink($path);
        }
    }

    public function test_apps_lists_the_configured_names(): void
    {
        self::assertSame(['ios', 'ios-sandbox', 'watch', 'android', 'broken'], Push::apps());
    }

    public function test_unknown_app(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Push app "nope" is not configured');

        $this->manager()->app('nope');
    }

    public function test_unknown_driver(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('driver "smoke-signals"');

        $this->manager()->app('broken');
    }

    public function test_unknown_team(): void
    {
        config()->set('push-notifications.apps.ios.team', 'ghost');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('team "ghost"');

        $this->manager()->app('ios');
    }

    public function test_missing_bundle_id(): void
    {
        config()->set('push-notifications.apps.ios.bundle_id', '');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('push-notifications.apps.ios.bundle_id is missing');

        $this->manager()->app('ios');
    }

    public function test_unknown_environment(): void
    {
        config()->set('push-notifications.apps.ios.environment', 'staging');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('unknown APNs environment');

        $this->manager()->app('ios');
    }

    public function test_sandbox_fallback_builds_both_clients(): void
    {
        config()->set('push-notifications.apns.sandbox_fallback', true);

        $sender = $this->manager()->app('ios');

        self::assertSame(Environment::Production, $sender->client()->environment());
        self::assertSame(Environment::Sandbox, $this->manager()->apnsClient('acme', Environment::Sandbox)->environment());
    }

    public function test_token_cache(): void
    {
        self::assertInstanceOf(LaravelCacheItemPool::class, $this->manager()->tokenCache());

        config()->set('push-notifications.token_cache.enabled', false);
        self::assertNull($this->manager()->tokenCache());
    }

    public function test_fcm_service_account_json_may_carry_whitespace(): void
    {
        config()->set('push-notifications.apps.android.service_account', "\n  ".Keys::serviceAccountJson('padded')."\n");

        self::assertSame('padded', $this->manager()->fcmClient('android')->account()->projectId);
    }
}
