<?php

declare(strict_types=1);

namespace Aporat\PushNotifications\Laravel\Events;

use Aporat\PushNotifications\Contracts\Message;
use Aporat\PushNotifications\Response;

/**
 * The service refused a push, or could not be reached. The response says
 * why and whether a retry makes sense.
 */
final readonly class NotificationFailed
{
    public function __construct(
        public string $app,
        public Message $message,
        public Response $response,
    ) {}
}
