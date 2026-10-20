<?php

declare(strict_types=1);

namespace Aporat\PushNotifications\Laravel\Channels;

use Aporat\PushNotifications\Laravel\PushManager;
use Aporat\PushNotifications\Notification as PushNotification;
use Aporat\PushNotifications\Response;
use Illuminate\Notifications\Notification;
use InvalidArgumentException;

/**
 * A Laravel notification channel. Use it as the channel's class name in
 * via(), give the notification a toPush($notifiable) method returning a
 * {@see PushNotification}, and let the notifiable say where it goes with
 * routeNotificationForPush(): one {@see PushTarget}, a list of them, or
 * the array form ['app' => ..., 'token' => ...].
 */
final class PushChannel
{
    public function __construct(
        private readonly PushManager $push,
    ) {}

    /**
     * @return array<int, Response> One response per target, in routing order.
     */
    public function send(mixed $notifiable, Notification $notification): array
    {
        $targets = $this->targets($notifiable, $notification);

        if ($targets === []) {
            return [];
        }

        if (! method_exists($notification, 'toPush')) {
            throw new InvalidArgumentException(sprintf('%s must define toPush($notifiable) to use the push channel.', $notification::class));
        }

        $push = $notification->toPush($notifiable);

        if (! $push instanceof PushNotification) {
            throw new InvalidArgumentException(sprintf('%s::toPush() must return a %s.', $notification::class, PushNotification::class));
        }

        $responses = [];

        foreach ($targets as $target) {
            $responses[] = $this->push->app($target->app)->notify($push, $target->token);
        }

        return $responses;
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
