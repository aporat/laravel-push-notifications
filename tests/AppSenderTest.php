<?php

declare(strict_types=1);

namespace Aporat\PushNotifications\Laravel\Tests;

use Aporat\PushNotifications\Apns\Message as ApnsMessage;
use Aporat\PushNotifications\Contracts\Message;
use Aporat\PushNotifications\Exceptions\InvalidMessageException;
use Aporat\PushNotifications\Fcm\Message as FcmMessage;
use Aporat\PushNotifications\Laravel\AppSender;
use Aporat\PushNotifications\Laravel\Events\DeviceTokenUnregistered;
use Aporat\PushNotifications\Laravel\Events\NotificationFailed;
use Aporat\PushNotifications\Laravel\Events\NotificationSent;
use Aporat\PushNotifications\Notification;
use Aporat\PushNotifications\Outcome;
use Aporat\PushNotifications\Platform;
use Aporat\PushNotifications\Response;
use Aporat\PushNotifications\Testing\FakeSender;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Event;

final class AppSenderTest extends TestCase
{
    private function sender(FakeSender $client, Platform $platform = Platform::Apns, ?FakeSender $fallback = null): AppSender
    {
        return new AppSender(
            name: 'ios',
            platform: $platform,
            client: $client,
            events: $this->app->make(Dispatcher::class),
            bundleId: $platform === Platform::Apns ? 'com.acme.app' : null,
            sandboxFallback: $fallback,
        );
    }

    public function test_notify_renders_for_the_app_and_raises_sent(): void
    {
        Event::fake();
        $client = new FakeSender;

        $response = $this->sender($client)->notify(Notification::create('Hi', 'There')->badge(1), 'ab12');

        self::assertTrue($response->succeeded());
        $message = $client->sent()[0];
        self::assertInstanceOf(ApnsMessage::class, $message);
        self::assertSame('com.acme.app', $message->headers()['apns-topic']);
        self::assertSame('ab12', $message->target());

        Event::assertDispatched(NotificationSent::class, fn (NotificationSent $e): bool => $e->app === 'ios' && $e->response === $response && $e->message === $message);
        Event::assertNotDispatched(NotificationFailed::class);
    }

    public function test_fcm_app_renders_fcm(): void
    {
        $client = new FakeSender;

        $this->sender($client, Platform::Fcm)->notify(Notification::create('Hi')->dataOnly(), 'tok');

        self::assertInstanceOf(FcmMessage::class, $client->sent()[0]);
        self::assertSame(['title' => 'Hi'], $client->sent()[0]->getData());
    }

    public function test_failure_raises_failed(): void
    {
        Event::fake();

        $response = $this->sender(new FakeSender(Outcome::RateLimited))->notify(Notification::create('Hi'), 'ab12');

        self::assertSame(Outcome::RateLimited, $response->outcome);
        Event::assertDispatched(NotificationFailed::class);
        Event::assertNotDispatched(NotificationSent::class);
        Event::assertNotDispatched(DeviceTokenUnregistered::class);
    }

    public function test_unregistered_raises_both_failed_and_unregistered(): void
    {
        Event::fake();

        $this->sender(new FakeSender(Outcome::Unregistered))->notify(Notification::create('Hi'), 'dead');

        Event::assertDispatched(NotificationFailed::class);
        Event::assertDispatched(DeviceTokenUnregistered::class, fn (DeviceTokenUnregistered $e): bool => $e->app === 'ios' && $e->token === 'dead' && $e->platform === Platform::Apns);
    }

    public function test_sandbox_fallback_retries_bad_device_tokens_only(): void
    {
        Event::fake();
        $production = new FakeSender(fn (Message $m): Response|Outcome => $m->target() === 'de01'
            ? new Response(Platform::Apns, $m->target(), Outcome::InvalidTarget, 400, 'BadDeviceToken')
            : Outcome::Delivered);
        $sandbox = new FakeSender;

        $responses = $this->sender($production, fallback: $sandbox)->notifyMany(Notification::create('Hi'), ['a' => 'ab01', 'b' => 'de01']);

        self::assertTrue($responses['a']->succeeded());
        self::assertTrue($responses['b']->succeeded());
        self::assertSame(2, $production->count());
        self::assertSame(1, $sandbox->count());
        self::assertSame('de01', $sandbox->sent()[0]->target());
        Event::assertDispatchedTimes(NotificationSent::class, 2);
        Event::assertNotDispatched(NotificationFailed::class);
    }

    public function test_sandbox_fallback_keeps_the_sandbox_answer(): void
    {
        $production = new FakeSender(fn (Message $m): Response => new Response(Platform::Apns, $m->target(), Outcome::InvalidTarget, 400, 'BadDeviceToken'));
        $sandbox = new FakeSender(Outcome::Unregistered);

        $response = $this->sender($production, fallback: $sandbox)->notify(Notification::create('Hi'), 'de01');

        self::assertSame(Outcome::Unregistered, $response->outcome);
    }

    public function test_other_failures_are_not_retried_on_sandbox(): void
    {
        $production = new FakeSender(Outcome::Unavailable);
        $sandbox = new FakeSender;

        $this->sender($production, fallback: $sandbox)->notify(Notification::create('Hi'), 'ab12');

        self::assertSame(0, $sandbox->count());
    }

    public function test_send_fills_in_the_bundle_id(): void
    {
        $client = new FakeSender;

        $this->sender($client)->send(ApnsMessage::background('ab12'));

        self::assertSame('com.acme.app', $client->sent()[0]->headers()['apns-topic']);
    }

    public function test_send_keeps_an_explicit_topic(): void
    {
        $client = new FakeSender;

        $this->sender($client)->send(ApnsMessage::voip('ab12')->topic('custom.voip'));

        self::assertSame('custom.voip', $client->sent()[0]->headers()['apns-topic']);
    }

    public function test_wrong_platform_message_is_refused(): void
    {
        $this->expectException(InvalidMessageException::class);
        $this->expectExceptionMessage('sends apns messages, not fcm');

        $this->sender(new FakeSender)->send(FcmMessage::to('t')->notification('x'));
    }
}
