<?php

declare(strict_types=1);

namespace Aporat\PushNotifications\Laravel\Facades;

use Aporat\PushNotifications\Apns\Client as ApnsClient;
use Aporat\PushNotifications\Apns\Environment;
use Aporat\PushNotifications\Fcm\Client as FcmClient;
use Aporat\PushNotifications\Laravel\AppSender;
use Aporat\PushNotifications\Laravel\PushManager;
use Aporat\PushNotifications\Laravel\Testing\PushFake;
use Illuminate\Support\Facades\Facade;
use RuntimeException;

/**
 * @method static AppSender app(string $name)
 * @method static list<string> apps()
 * @method static ApnsClient apnsClient(string $app, Environment $environment)
 * @method static FcmClient fcmClient(string $app)
 *
 * @see PushManager
 *
 * @mixin PushManager
 */
final class Push extends Facade
{
    /**
     * Replace the manager with a fake that records every push instead of
     * sending it. Returns the fake, for its assertions.
     */
    public static function fake(): PushFake
    {
        $app = self::getFacadeApplication();

        if ($app === null) {
            throw new RuntimeException('The Push facade has no application to fake.');
        }

        $fake = new PushFake($app);

        self::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return 'push-notifications';
    }
}
