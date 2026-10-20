<?php

declare(strict_types=1);

namespace Aporat\PushNotifications\Laravel\Exceptions;

use Aporat\PushNotifications\Exceptions\PushException;
use Aporat\PushNotifications\Response;

/**
 * Thrown by the notification channel when a send reached no device at all
 * and at least one of the failures is worth retrying, so a queued
 * notification's $tries and backoff take over. A partial success never
 * throws: a retry would send the notification again to the devices that
 * already have it.
 */
final class DeliveryFailedException extends PushException
{
    /**
     * @param  list<Response>  $responses  Every target's answer, in routing order.
     */
    public function __construct(
        public readonly array $responses,
    ) {
        $reasons = array_values(array_unique(array_map(
            static fn (Response $response): string => $response->reason ?? $response->outcome->value,
            array_filter($responses, static fn (Response $response): bool => ! $response->succeeded()),
        )));

        parent::__construct(sprintf(
            'The push reached none of its %d target(s) and can be retried (%s).',
            count($responses),
            implode(', ', $reasons),
        ));
    }
}
