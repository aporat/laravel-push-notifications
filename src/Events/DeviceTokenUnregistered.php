<?php

declare(strict_types=1);

namespace Aporat\PushNotifications\Laravel\Events;

use Aporat\PushNotifications\Platform;
use Aporat\PushNotifications\Response;

/**
 * The service reported the device token permanently inactive for this app
 * (APNs 410, FCM UNREGISTERED): the app was uninstalled or the token
 * rotated. Listen for this to clear the token from storage. Raised after
 * {@see NotificationFailed} for the same push.
 */
final readonly class DeviceTokenUnregistered
{
    public function __construct(
        public string $app,
        public Platform $platform,
        public string $token,
        public Response $response,
    ) {}
}
