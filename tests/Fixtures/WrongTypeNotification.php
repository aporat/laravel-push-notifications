<?php

declare(strict_types=1);

namespace Aporat\PushNotifications\Laravel\Tests\Fixtures;

use Aporat\PushNotifications\Fcm\Message as FcmMessage;
use Aporat\PushNotifications\Laravel\Channels\PushChannel;
use Illuminate\Notifications\Notification;

final class WrongTypeNotification extends Notification
{
    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return [PushChannel::class];
    }

    public function toApns(mixed $notifiable, string $token): FcmMessage
    {
        return FcmMessage::to($token)->notification('x');
    }
}
