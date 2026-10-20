<?php

declare(strict_types=1);

namespace Aporat\PushNotifications\Laravel\Channels;

use Aporat\PushNotifications\Laravel\AppSender;
use Aporat\PushNotifications\Laravel\Exceptions\DeliveryFailedException;
use Aporat\PushNotifications\Laravel\PushManager;
use Aporat\PushNotifications\Notification as PushNotification;
use Aporat\PushNotifications\Response;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Notification;
use InvalidArgumentException;

/**
 * A Laravel notification channel. Use it as the channel's class name in
 * via(), give the notification a toPush($notifiable) method returning a
 * {@see PushNotification}, and let the notifiable say where it goes with
 * routeNotificationForPush(): one {@see PushTarget}, a list of them, or
 * the array form ['app' => ..., 'token' => ...].
 *
 * Targets are grouped by app and sent concurrently through each app's
 * connection. Every app is resolved before anything is sent, so a bad app
 * name fails the whole send rather than leaving earlier targets notified
 * and a retry notifying them again. Each failed target raises Laravel's
 * {@see NotificationFailed} with the app, target and response; when no
 * target was reached and a failure is retryable, the send throws so a
 * queued notification is retried.
 */
final class PushChannel
{
    public function __construct(
        private readonly PushManager $push,
        private readonly Container $container,
    ) {}

    /**
     * @return list<Response> One response per target, in routing order.
     *
     * @throws DeliveryFailedException
     */
    public function send(mixed $notifiable, Notification $notification): array
    {
        $targets = $this->targets($notifiable, $notification);

        if ($targets === []) {
            return [];
        }

        $push = $this->payload($notifiable, $notification);

        /** @var array<string, array<int, string>> $tokensByApp */
        $tokensByApp = [];

        foreach ($targets as $index => $target) {
            $tokensByApp[$target->app][$index] = $target->token;
        }

        /** @var array<string, AppSender> $senders */
        $senders = [];

        foreach (array_keys($tokensByApp) as $app) {
            $senders[$app] = $this->push->app($app);
        }

        /** @var array<int, Response> $responses */
        $responses = [];

        foreach ($tokensByApp as $app => $tokens) {
            foreach ($senders[$app]->notifyMany($push, $tokens) as $index => $response) {
                $responses[$index] = $response;
            }
        }

        ksort($responses);
        $responses = array_values($responses);

        $delivered = false;
        $retryable = false;

        /** @var Dispatcher $events */
        $events = $this->container->make(Dispatcher::class);

        foreach ($responses as $index => $response) {
            if ($response->succeeded()) {
                $delivered = true;

                continue;
            }

            $retryable = $retryable || $response->isRetryable();

            $events->dispatch(new NotificationFailed($notifiable, $notification, 'push', [
                'app' => $targets[$index]->app,
                'target' => $response->target,
                'response' => $response,
            ]));
        }

        if (! $delivered && $retryable) {
            throw new DeliveryFailedException($responses);
        }

        return $responses;
    }

    private function payload(mixed $notifiable, Notification $notification): PushNotification
    {
        if (! method_exists($notification, 'toPush')) {
            throw new InvalidArgumentException(sprintf('%s must define toPush($notifiable) to use the push channel.', $notification::class));
        }

        $push = $notification->toPush($notifiable);

        if (! $push instanceof PushNotification) {
            throw new InvalidArgumentException(sprintf('%s::toPush() must return a %s.', $notification::class, PushNotification::class));
        }

        return $push;
    }

    /**
     * @return list<PushTarget>
     */
    private function targets(mixed $notifiable, Notification $notification): array
    {
        if (! is_object($notifiable) || ! method_exists($notifiable, 'routeNotificationFor')) {
            return [];
        }

        $routes = $notifiable->routeNotificationFor('push', $notification);

        if ($routes === null || $routes === []) {
            return [];
        }

        if ($routes instanceof PushTarget || (is_array($routes) && isset($routes['app']))) {
            $routes = [$routes];
        }

        if (! is_array($routes)) {
            throw new InvalidArgumentException('routeNotificationForPush() must return a PushTarget, an [app, token] array, or a list of either.');
        }

        $targets = [];

        foreach ($routes as $route) {
            if ($route instanceof PushTarget) {
                $targets[] = $route;

                continue;
            }

            if (is_array($route) && is_string($route['app'] ?? null) && is_string($route['token'] ?? null)) {
                $targets[] = new PushTarget($route['app'], $route['token']);

                continue;
            }

            throw new InvalidArgumentException('Each push route must be a PushTarget or an array with "app" and "token".');
        }

        return $targets;
    }
}
