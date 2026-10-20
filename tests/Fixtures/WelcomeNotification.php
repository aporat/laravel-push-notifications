<?php

declare(strict_types=1);

namespace Aporat\PushNotifications\Laravel\Tests\Fixtures;

use Aporat\PushNotifications\Laravel\Channels\PushChannel;
use Aporat\PushNotifications\Notification as PushNotification;
use Illuminate\Notifications\Notification;

final class WelcomeNotification extends Notification
{
    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return [PushChannel::class];
    }

    public function toPush(mixed $notifiable): PushNotification
    {
        return PushNotification::create('Welcome', 'Glad you are here')->badge(1);
    }
}
