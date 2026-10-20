<?php

declare(strict_types=1);

namespace Aporat\PushNotifications\Laravel\Tests\Fixtures;

use Aporat\PushNotifications\Apns\Message as ApnsMessage;
use Aporat\PushNotifications\Apns\Payload;
use Aporat\PushNotifications\Fcm\Message as FcmMessage;
use Aporat\PushNotifications\Laravel\Channels\PushChannel;
use Illuminate\Notifications\Notification;

/**
 * A notification with a builder per platform and no toPush(): a Live
 * Activity update on APNs, a data-only message on FCM.
 */
final class ScoreUpdateNotification extends Notification
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
        return ApnsMessage::liveActivity($token, Payload::create()->event('update')->timestamp(1700000000)->contentState(['score' => 3]));
    }

    public function toFcm(mixed $notifiable, string $token): FcmMessage
    {
        return FcmMessage::to($token)->data(['score' => 3]);
    }
}
