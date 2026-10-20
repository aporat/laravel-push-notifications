<?php

declare(strict_types=1);

namespace Aporat\PushNotifications\Laravel;

use Aporat\PushNotifications\Apns\Message as ApnsMessage;
use Aporat\PushNotifications\Apns\Reason;
use Aporat\PushNotifications\Contracts\Message;
use Aporat\PushNotifications\Contracts\Sender;
use Aporat\PushNotifications\Exceptions\InvalidMessageException;
use Aporat\PushNotifications\Fcm\Target;
use Aporat\PushNotifications\Laravel\Events\DeviceTokenUnregistered;
use Aporat\PushNotifications\Laravel\Events\NotificationFailed;
use Aporat\PushNotifications\Laravel\Events\NotificationSent;
use Aporat\PushNotifications\Notification;
use Aporat\PushNotifications\Platform;
use Aporat\PushNotifications\Response;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * The sender for one configured app: renders cross-platform notifications
 * for its platform, fills in its bundle ID, retries development tokens on
 * the sandbox when configured, and raises an event for every answer.
 */
final class AppSender implements Sender
{
    public function __construct(
        public readonly string $name,
        public readonly Platform $platform,
        private readonly Sender $client,
        private readonly Dispatcher $events,
        private readonly ?string $bundleId = null,
        private readonly ?Sender $sandboxFallback = null,
    ) {}

    /**
     * Render a notification for one device of this app and send it.
     */
    public function notify(Notification $notification, Target|string $target): Response
    {
        return $this->send($this->render($notification, $target));
    }

    /**
     * Render a notification for many devices and send them concurrently.
     * Results are keyed like the input.
     *
     * @param  iterable<array-key, Target|string>  $targets
     * @return array<array-key, Response>
     */
    public function notifyMany(Notification $notification, iterable $targets): array
    {
        $messages = [];

        foreach ($targets as $key => $target) {
            $messages[$key] = $this->render($notification, $target);
        }

        return $this->sendMany($messages);
    }

    /**
     * The platform message a notification renders to for this app.
     *
     * @throws InvalidMessageException
     */
    public function render(Notification $notification, Target|string $target): Message
    {
        return match ($this->platform) {
            Platform::Apns => $notification->toApns(
                $target instanceof Target ? $target->value : $target,
                (string) $this->bundleId,
            ),
            Platform::Fcm => $notification->toFcm($target),
        };
    }

    public function send(Message $message): Response
    {
        return $this->sendMany([$message])[0];
    }

    public function sendMany(iterable $messages): array
    {
        $prepared = [];

        foreach ($messages as $key => $message) {
            $prepared[$key] = $this->prepare($message);
        }

        $responses = $this->client->sendMany($prepared);

        if ($this->sandboxFallback !== null) {
            $responses = $this->retryOnSandbox($prepared, $responses);
        }

        foreach ($responses as $key => $response) {
            $this->dispatch($prepared[$key], $response);
        }

        return $responses;
    }

    public function bundleId(): ?string
    {
        return $this->bundleId;
    }

    /**
     * The platform client behind this app, for anything the sender does not expose.
     */
    public function client(): Sender
    {
        return $this->client;
    }

    /**
     * @throws InvalidMessageException
     */
    private function prepare(Message $message): Message
    {
        if ($message->platform() !== $this->platform) {
            throw new InvalidMessageException(sprintf('App "%s" sends %s messages, not %s.', $this->name, $this->platform->value, $message->platform()->value));
        }

        if ($message instanceof ApnsMessage && $message->resolvedTopic() === null && $this->bundleId !== null) {
            $message->bundleId($this->bundleId);
        }

        return $message;
    }

    /**
     * A token from a development build is only known to the sandbox
     * gateway; production answers BadDeviceToken. Send those once more
     * through the sandbox and keep its answer.
     *
     * @param  array<array-key, Message>  $messages
     * @param  array<array-key, Response>  $responses
     * @return array<array-key, Response>
     */
    private function retryOnSandbox(array $messages, array $responses): array
    {
        $retry = [];

        foreach ($responses as $key => $response) {
            if ($response->statusCode === 400 && $response->reason === Reason::BadDeviceToken->value) {
                $retry[$key] = $messages[$key];
            }
        }

        if ($retry === [] || $this->sandboxFallback === null) {
            return $responses;
        }

        foreach ($this->sandboxFallback->sendMany($retry) as $key => $response) {
            $responses[$key] = $response;
        }

        return $responses;
    }

    private function dispatch(Message $message, Response $response): void
    {
        if ($response->succeeded()) {
            $this->events->dispatch(new NotificationSent($this->name, $message, $response));

            return;
        }

        $this->events->dispatch(new NotificationFailed($this->name, $message, $response));

        if ($response->tokenIsUnregistered()) {
            $this->events->dispatch(new DeviceTokenUnregistered($this->name, $this->platform, $message->target(), $response));
        }
    }
}
