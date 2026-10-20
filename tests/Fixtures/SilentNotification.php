<?php

declare(strict_types=1);

namespace Aporat\PushNotifications\Laravel\Tests\Fixtures;

use Aporat\PushNotifications\Laravel\Channels\PushChannel;
use Illuminate\Notifications\Notification;

final class SilentNotification extends Notification
{
    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return [PushChannel::class];
    }
}
