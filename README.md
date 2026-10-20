# Laravel Push Notifications

Laravel integration for [aporat/push-notifications](https://github.com/aporat/push-notifications): Apple Push Notification service (APNs) and Firebase Cloud Messaging (FCM) pushes, configured per app, with one persistent HTTP/2 connection per team or project, events for every answer, a notification channel, and a fake for tests.

[![Latest Stable Version](https://img.shields.io/packagist/v/aporat/laravel-push-notifications.svg?style=flat-square&logo=composer)](https://packagist.org/packages/aporat/laravel-push-notifications)
[![Downloads](https://img.shields.io/packagist/dt/aporat/laravel-push-notifications.svg?style=flat-square&logo=composer)](https://packagist.org/packages/aporat/laravel-push-notifications)
[![codecov](https://codecov.io/github/aporat/laravel-push-notifications/graph/badge.svg)](https://codecov.io/github/aporat/laravel-push-notifications)
[![Laravel Version](https://img.shields.io/badge/Laravel-13.x-orange.svg?style=flat-square)](https://laravel.com/docs/13.x)
![GitHub Actions](https://img.shields.io/github/actions/workflow/status/aporat/laravel-push-notifications/ci.yml?style=flat-square)
[![License](https://img.shields.io/packagist/l/aporat/laravel-push-notifications.svg?style=flat-square)](https://github.com/aporat/laravel-push-notifications/blob/main/LICENSE)

---

## ✨ Features

- `Push::app('name')` resolves a configured app to a sender that knows its platform, bundle ID and credentials. APNs clients are shared per team and environment, FCM clients per app, and all of them live for the life of the process, so a queue worker keeps its connections and tokens between jobs
- Provider and access tokens shared across workers through any Laravel cache store
- Events for every answer: `NotificationSent`, `NotificationFailed`, and `DeviceTokenUnregistered` when a service reports the token dead, so one listener clears tokens for every app
- Optional sandbox fallback: a production push answered `BadDeviceToken` is retried once on the sandbox gateway, where tokens from Xcode builds live
- A notification channel, so `$user->notify(new Welcome)` reaches every device the user routes to, on either platform
- `Push::fake()` with `assertSent`, `assertSentTo`, `assertNotSent`, `assertSentCount` and `assertNothingSent`
- `php artisan push:test` to check credentials and a device token from the terminal

The sending itself, the payload builders and the normalized responses are the core library's. Its [README](https://github.com/aporat/push-notifications#readme) documents the `Notification`, the platform messages and every `Outcome`.

---

## 🛠 Installation

```bash
composer require aporat/laravel-push-notifications
```

Requires PHP 8.4+, Laravel 12 or 13, and `ext-curl` built with HTTP/2 support. Publish the config:

```bash
php artisan vendor:publish --tag=config --provider="Aporat\PushNotifications\Laravel\PushNotificationsServiceProvider"
```

---

## ⚙️ Configuration

Teams hold APNs auth keys; apps name their platform and credentials:

```php
return [
    'teams' => [
        'acme' => [
            'team_id' => env('ACME_TEAM_ID'),
            'key_id' => env('ACME_APNS_KEY_ID'),
            // The .p8 file's path, or its PEM contents (flattened line breaks are restored).
            'private_key' => env('ACME_APNS_PRIVATE_KEY'),
        ],
    ],

    'apps' => [
        'acme-ios' => [
            'driver' => 'apns',
            'bundle_id' => 'com.acme.app',
            'team' => 'acme',
            'environment' => 'production', // or 'sandbox'; defaults to apns.environment
        ],
        'acme-watch' => [
            'driver' => 'apns',
            'bundle_id' => 'com.acme.watch',
            'team' => 'acme', // same team: shares the connection and the provider token
        ],
        'acme-android' => [
            'driver' => 'fcm',
            // The service account key file's path, or its JSON contents.
            'service_account' => env('ACME_FIREBASE_SERVICE_ACCOUNT_JSON'),
        ],
    ],
];
```

The rest of the file covers logging (`PUSH_LOG_CHANNEL`), the token cache store (`PUSH_TOKEN_CACHE_STORE`; use redis or database behind more than one server), HTTP timeouts and batch concurrency, the APNs port and default environment, and `PUSH_APNS_SANDBOX_FALLBACK`.

---

## 🚀 Sending

```php
use Aporat\PushNotifications\Laravel\Facades\Push;
use Aporat\PushNotifications\Notification;

$notification = Notification::create('New collab request', 'Alice wants to work with you')
    ->badge(3)
    ->sound('default')
    ->data(['type' => 'collab', 'id' => 42])
    ->collapseId('collab-42');

$response = Push::app('acme-ios')->notify($notification, $device->apns_token);
$response = Push::app('acme-android')->notify($notification, $device->fcm_token);

// Many devices of one app, concurrently over the one connection. Results keep the input keys.
$responses = Push::app('acme-ios')->notifyMany($notification, $tokensKeyedByDeviceId);
```

A platform message goes through the same app with `send()` and `sendMany()`. An APNs message without a topic gets the app's bundle ID:

```php
use Aporat\PushNotifications\Apns\Message;
use Aporat\PushNotifications\Apns\Payload;

Push::app('acme-ios')->send(Message::liveActivity($activityToken, Payload::create()
    ->event('update')
    ->timestamp(time())
    ->contentState(['eta' => 12])));
```

Every call returns the core library's `Response`: `succeeded()`, `tokenIsUnregistered()`, `isRetryable()`, the raw `reason`, the service's message ID. The platform client itself is on `Push::app('acme-ios')->client()`.

---

## 📣 Events

| Event | When | Carries |
|---|---|---|
| `NotificationSent` | The service accepted the push | app, message, response |
| `NotificationFailed` | The service refused it, or could not be reached | app, message, response |
| `DeviceTokenUnregistered` | APNs 410 or FCM `UNREGISTERED`; raised after `NotificationFailed` | app, platform, token, response |

One listener keeps token storage clean for every app:

```php
use Aporat\PushNotifications\Laravel\Events\DeviceTokenUnregistered;

Event::listen(DeviceTokenUnregistered::class, function (DeviceTokenUnregistered $event) {
    Device::where('app', $event->app)->where('token', $event->token)->update(['token' => null]);
});
```

---

## 🔔 Notification channel

Use the channel's class in `via()`, return a core `Notification` from `toPush()`, and route from the notifiable:

```php
use Aporat\PushNotifications\Laravel\Channels\PushChannel;
use Aporat\PushNotifications\Laravel\Channels\PushTarget;
use Aporat\PushNotifications\Notification as PushNotification;

class CollabRequested extends Notification
{
    public function via($notifiable): array
    {
        return [PushChannel::class];
    }

    public function toPush($notifiable): PushNotification
    {
        return PushNotification::create('New collab request', $this->from->name.' wants to work with you')
            ->data(['type' => 'collab', 'id' => $this->collab->id]);
    }
}

class User extends Authenticatable
{
    use Notifiable;

    public function routeNotificationForPush(): array
    {
        return $this->devices
            ->map(fn (Device $d) => new PushTarget($d->app, $d->token))
            ->all();
    }
}
```

`routeNotificationForPush()` may return one `PushTarget`, one `['app' => ..., 'token' => ...]` array, or a list of either. Each target is sent through its own app, so one user with an iPhone and an Android phone gets both.

---

## 🧪 Testing

```php
use Aporat\PushNotifications\Laravel\Facades\Push;
use Aporat\PushNotifications\Outcome;

$push = Push::fake();

$user->notify(new CollabRequested($collab));

$push->assertSentTo('acme-ios', $user->apns_token)
    ->assertSent('acme-ios', fn (Message $m) => str_contains($m->body(), '"type":"collab"'))
    ->assertSentCount(1, 'acme-android')
    ->assertNotSent('acme-watch');

// Script the answers; events still fire, so listeners can be tested.
Push::fake()->respondWith(Outcome::Unregistered);
```

Apps still come from config, so the fake renders and raises events exactly as production would; only the HTTP leaves.

---

## 🖥 Console

```bash
php artisan push:test acme-ios <device-token> --title="Hello" --body="From the server" --badge=1 --data=type=test
```

Prints the response as a table and exits non-zero when the push was refused.

---

## 📄 License

MIT. See [LICENSE](LICENSE).
