<?php

declare(strict_types=1);

namespace Aporat\PushNotifications\Laravel\Tests\Fixtures;

use Aporat\PushNotifications\Laravel\Channels\PushTarget;
use Illuminate\Notifications\Notifiable;

final class Device
{
    use Notifiable;

    /**
     * @param  PushTarget|array{app: string, token: string}|list<PushTarget|array{app: string, token: string}>|null  $routes
     */
    public function __construct(private readonly PushTarget|array|null $routes) {}

    /**
     * @return PushTarget|array{app: string, token: string}|list<PushTarget|array{app: string, token: string}>|null
     */
    public function routeNotificationForPush(): PushTarget|array|null
    {
        return $this->routes;
    }

    public function getKey(): int
    {
        return 1;
    }
}
