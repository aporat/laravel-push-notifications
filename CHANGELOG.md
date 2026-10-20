# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Changed
- `PushChannel` resolves every routed app before sending, groups targets by app and sends each
  group concurrently with `notifyMany()`. Each refused target raises Laravel's
  `Illuminate\Notifications\Events\NotificationFailed` with the app, target and `Response` in its
  data. When no target was reached and a failure is retryable, the send throws the new
  `DeliveryFailedException`, so queued notifications retry; a partial success never throws.
- `AppSender` resolves the event dispatcher when it dispatches rather than capturing it at
  construction, since senders outlive the request that built them under Octane. Its constructor now
  takes the container.
- An FCM app's `service_account` is recognised as JSON with `json_validate()` rather than by the
  presence of a brace.

### Added
- `logging.redact_tokens` (`PUSH_LOG_REDACT_TOKENS`): shorten device tokens in the clients' log lines.
- A regression test that a credential failure (a key that cannot sign, a rejected service account)
  surfaces from `AppSender::notify()` as an exception rather than a retryable response, which depends
  on `aporat/push-notifications` 0.1.1 or later.
- README sections on credentials from a secrets manager, the token cache's trust level, and the
  channel's failure and retry behaviour.

### Added in 0.1.0
- `PushManager` and the `Push` facade: `Push::app('name')` resolves a configured app to an
  `AppSender`. APNs clients are shared per team and environment and FCM clients per app, all
  held for the life of the process, with provider and access tokens shared across workers through
  a Laravel cache store (`push-notifications.token_cache`).
- `AppSender::notify()` and `notifyMany()` render a cross-platform `Notification` for the app's
  platform; `send()` and `sendMany()` carry platform messages, filling in the app's bundle ID
  when an APNs message has no topic.
- Events `NotificationSent`, `NotificationFailed` and `DeviceTokenUnregistered`, raised for
  every answer.
- `push-notifications.apns.sandbox_fallback`: a production push answered `BadDeviceToken` is
  retried once on the sandbox gateway and the sandbox answer kept.
- `PushChannel` and `PushTarget`: a notification channel routed by
  `routeNotificationForPush()`, one target per device on either platform.
- `Push::fake()` returning a `PushFake` with `assertSent`, `assertNotSent`, `assertSentTo`,
  `assertSentCount`, `assertNothingSent`, `sent()` and `respondWith()`.
- `push:test` Artisan command.
- A PSR-6 pool over the Laravel cache (`Support\Psr6`) for the core library's token caches.

### Notes
- Until `aporat/push-notifications` is on Packagist, `composer.json` resolves it from a sibling
  checkout (`../push-notifications`) pinned to 0.1.2 through a path repository. Consumers' Composer
  ignores that entry; it only serves this package's own development and CI.
