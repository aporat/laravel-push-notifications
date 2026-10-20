# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses
[Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
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
