<?php

declare(strict_types=1);

namespace Aporat\PushNotifications\Laravel\Events;

use Aporat\PushNotifications\Contracts\Message;
use Aporat\PushNotifications\Response;

/**
 * The service accepted a push for delivery.
 */
final readonly class NotificationSent
{
    public function __construct(
        public string $app,
        public Message $message,
        public Response $response,
    ) {}
}
