<?php

declare(strict_types=1);

namespace Aporat\PushNotifications\Laravel\Channels;

/**
 * One device a notifiable routes pushes to: which app, and its token.
 */
final readonly class PushTarget
{
    public function __construct(
        public string $app,
        public string $token,
    ) {}
}
