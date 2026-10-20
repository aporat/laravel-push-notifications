<?php

declare(strict_types=1);

namespace Aporat\PushNotifications\Laravel\Tests;

use Aporat\PushNotifications\Apns\Message as ApnsMessage;
use Aporat\PushNotifications\Contracts\Message;
use Aporat\PushNotifications\Laravel\Channels\PushChannel;
use Aporat\PushNotifications\Laravel\Channels\PushTarget;
use Aporat\PushNotifications\Laravel\Facades\Push;
use Aporat\PushNotifications\Laravel\Tests\Fixtures\Device;
use Aporat\PushNotifications\Laravel\Tests\Fixtures\SilentNotification;
use Aporat\PushNotifications\Laravel\Tests\Fixtures\WelcomeNotification;
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
}
