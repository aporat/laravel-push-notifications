<?php

declare(strict_types=1);

namespace Aporat\PushNotifications\Laravel\Tests\Fixtures;

use Aporat\PushNotifications\Apns\Message as ApnsMessage;
use Aporat\PushNotifications\Laravel\Channels\PushChannel;
use Aporat\PushNotifications\Notification as PushNotification;
use Illuminate\Notifications\Notification;

/**
 * toApns() for iOS, toPush() for everything else.
 */
final class ApnsOnlyNotification extends Notification
{
    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return [PushChannel::class];
    }

    public function toApns(mixed $notifiable, string $token): ApnsMessage
    {
        return ApnsMessage::background($token);
    }

    public function toPush(mixed $notifiable): PushNotification
    {
        return PushNotification::create('Fallback');
    }
}
