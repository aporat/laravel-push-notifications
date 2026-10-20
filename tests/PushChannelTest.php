<?php

declare(strict_types=1);

namespace Aporat\PushNotifications\Laravel\Tests;

use Aporat\PushNotifications\Apns\Message as ApnsMessage;
use Aporat\PushNotifications\Contracts\Message;
use Aporat\PushNotifications\Exceptions\ConfigurationException;
use Aporat\PushNotifications\Laravel\Channels\PushChannel;
use Aporat\PushNotifications\Laravel\Channels\PushTarget;
use Aporat\PushNotifications\Laravel\Exceptions\DeliveryFailedException;
use Aporat\PushNotifications\Laravel\Facades\Push;
use Aporat\PushNotifications\Laravel\Tests\Fixtures\Device;
use Aporat\PushNotifications\Laravel\Tests\Fixtures\SilentNotification;
use Aporat\PushNotifications\Laravel\Tests\Fixtures\WelcomeNotification;
use Aporat\PushNotifications\Outcome;
use Aporat\PushNotifications\Response;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

final class PushChannelTest extends TestCase
{
    public function test_sends_to_every_routed_target(): void
    {
        $fake = Push::fake();
        $device = new Device([new PushTarget('ios', 'ab12'), ['app' => 'android', 'token' => 'tok']]);

        $device->notify(new WelcomeNotification);

        $fake->assertSentTo('ios', 'ab12')
            ->assertSentTo('android', 'tok')
            ->assertSentCount(2)
            ->assertSent('ios', fn (Message $m): bool => $m instanceof ApnsMessage && str_contains($m->body(), '"title":"Welcome"'));
    }

    public function test_single_route_forms(): void
    {
        $fake = Push::fake();

        (new Device(new PushTarget('ios', 'a1')))->notify(new WelcomeNotification);
        (new Device(['app' => 'ios', 'token' => 'b2']))->notify(new WelcomeNotification);

        $fake->assertSentTo('ios', 'a1')->assertSentTo('ios', 'b2')->assertSentCount(2);
    }

    public function test_no_routes_sends_nothing(): void
    {
        $fake = Push::fake();

        (new Device(null))->notify(new WelcomeNotification);
        (new Device([]))->notify(new WelcomeNotification);

        $fake->assertNothingSent();
    }

    public function test_returns_the_responses(): void
    {
        Push::fake();
        $channel = $this->app->make(PushChannel::class);

        $responses = $channel->send(new Device([new PushTarget('ios', 'a'), new PushTarget('ios', 'b')]), new WelcomeNotification);

        self::assertCount(2, $responses);
        self::assertSame('b', $responses[1]->target);
    }

    public function test_notification_without_to_push_is_refused(): void
    {
        Push::fake();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must define toPush');

        (new Device(new PushTarget('ios', 'ab12')))->notify(new SilentNotification);
    }

    public function test_bad_route_is_refused(): void
    {
        Push::fake();

        $this->expectException(InvalidArgumentException::class);

        (new Device([['token' => 'no app']]))->notify(new WelcomeNotification);
    }

    public function test_responses_keep_routing_order_across_apps(): void
    {
        Push::fake();
        $channel = $this->app->make(PushChannel::class);

        $responses = $channel->send(new Device([
            new PushTarget('ios', 'a1'),
            new PushTarget('android', 'tok'),
            new PushTarget('ios', 'b2'),
        ]), new WelcomeNotification);

        self::assertSame(['a1', 'tok', 'b2'], array_map(static fn (Response $r): string => $r->target, $responses));
    }

    public function test_every_app_is_resolved_before_anything_is_sent(): void
    {
        $fake = Push::fake();

        try {
            (new Device([new PushTarget('ios', 'a1'), new PushTarget('nope', 'x')]))->notify(new WelcomeNotification);
            self::fail('Expected a ConfigurationException.');
        } catch (ConfigurationException) {
            $fake->assertNothingSent();
        }
    }

    public function test_failed_targets_raise_laravels_notification_failed(): void
    {
        Event::fake([NotificationFailed::class]);
        Push::fake()->respondWith(fn (Message $m): Outcome => $m->target() === 'dead' ? Outcome::Unregistered : Outcome::Delivered);
        $device = new Device([new PushTarget('ios', 'a1'), new PushTarget('ios', 'dead')]);

        $device->notify(new WelcomeNotification);

        Event::assertDispatchedTimes(NotificationFailed::class, 1);
        Event::assertDispatched(NotificationFailed::class, static fn (NotificationFailed $e): bool => $e->notifiable === $device
            && $e->notification instanceof WelcomeNotification
            && $e->channel === 'push'
            && $e->data['app'] === 'ios'
            && $e->data['target'] === 'dead'
            && $e->data['response'] instanceof Response
            && $e->data['response']->tokenIsUnregistered());
    }

    public function test_throws_when_nothing_was_delivered_and_a_failure_is_retryable(): void
    {
        Push::fake()->respondWith(Outcome::Unavailable);

        try {
            (new Device([new PushTarget('ios', 'a1'), new PushTarget('android', 'tok')]))->notify(new WelcomeNotification);
            self::fail('Expected a DeliveryFailedException.');
        } catch (DeliveryFailedException $e) {
            self::assertCount(2, $e->responses);
            self::assertStringContainsString('none of its 2 target(s)', $e->getMessage());
            self::assertStringContainsString('FakeUnavailable', $e->getMessage());
        }
    }

    public function test_partial_delivery_does_not_throw(): void
    {
        Push::fake()->respondWith(fn (Message $m): Outcome => $m->target() === 'a1' ? Outcome::Delivered : Outcome::Unavailable);

        $responses = $this->app->make(PushChannel::class)->send(new Device([new PushTarget('ios', 'a1'), new PushTarget('ios', 'b2')]), new WelcomeNotification);

        self::assertTrue($responses[0]->succeeded());
        self::assertTrue($responses[1]->isRetryable());
    }

    public function test_permanent_failures_do_not_throw(): void
    {
        Push::fake()->respondWith(Outcome::Unregistered);

        $responses = $this->app->make(PushChannel::class)->send(new Device([new PushTarget('ios', 'a1')]), new WelcomeNotification);

        self::assertTrue($responses[0]->tokenIsUnregistered());
    }
}
