<?php

declare(strict_types=1);

namespace Aporat\PushNotifications\Laravel\Tests;

use Aporat\PushNotifications\Apns\Message as ApnsMessage;
use Aporat\PushNotifications\Contracts\Message;
use Aporat\PushNotifications\Exceptions\ConfigurationException;
use Aporat\PushNotifications\Laravel\Events\DeviceTokenUnregistered;
use Aporat\PushNotifications\Laravel\Facades\Push;
use Aporat\PushNotifications\Laravel\PushManager;
use Aporat\PushNotifications\Laravel\Testing\PushFake;
use Aporat\PushNotifications\Notification;
use Aporat\PushNotifications\Outcome;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\AssertionFailedError;

final class PushFakeTest extends TestCase
{
    public function test_fake_replaces_the_manager(): void
    {
        $fake = Push::fake();

        self::assertInstanceOf(PushFake::class, $this->app->make('push-notifications'));
        self::assertSame($fake, $this->app->make(PushManager::class));
        self::assertSame($fake, Push::getFacadeRoot());
    }

    public function test_records_and_asserts(): void
    {
        $fake = Push::fake();

        $response = Push::app('ios')->notify(Notification::create('Hi'), 'ab12');
        Push::app('android')->notify(Notification::create('Hi'), 'tok');

        self::assertTrue($response->succeeded());
        $fake->assertSent('ios')
            ->assertSentTo('ios', 'ab12')
            ->assertSentTo('android', 'tok')
            ->assertSent('ios', fn (Message $m): bool => $m instanceof ApnsMessage && $m->headers()['apns-topic'] === 'com.acme.app')
            ->assertNotSent('ios', fn (Message $m): bool => $m->target() === 'other')
            ->assertSentCount(2)
            ->assertSentCount(1, 'ios');

        self::assertCount(2, $fake->sent());
        self::assertCount(1, $fake->sent('android'));
        self::assertSame([], $fake->sent('watch'));
    }

    public function test_assert_nothing_sent(): void
    {
        $fake = Push::fake();
        $fake->assertNothingSent();

        Push::app('ios')->notify(Notification::create('Hi'), 'ab12');

        $this->expectException(AssertionFailedError::class);
        $fake->assertNothingSent();
    }

    public function test_assert_sent_fails_when_nothing_matches(): void
    {
        $fake = Push::fake();

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('No push was sent through app "ios"');

        $fake->assertSent('ios');
    }

    public function test_responses_can_be_scripted_and_still_raise_events(): void
    {
        Event::fake();
        Push::fake()->respondWith(Outcome::Unregistered);

        $response = Push::app('ios')->notify(Notification::create('Hi'), 'dead');

        self::assertTrue($response->tokenIsUnregistered());
        Event::assertDispatched(DeviceTokenUnregistered::class, fn (DeviceTokenUnregistered $e): bool => $e->token === 'dead');
    }

    public function test_responder_applies_to_apps_built_earlier(): void
    {
        $fake = Push::fake();
        Push::app('ios');
        $fake->respondWith(Outcome::RateLimited);

        self::assertSame(Outcome::RateLimited, Push::app('ios')->notify(Notification::create('Hi'), 'ab12')->outcome);
    }

    public function test_unknown_apps_still_fail(): void
    {
        Push::fake();

        $this->expectException(ConfigurationException::class);

        Push::app('nope');
    }

    public function test_bad_driver_still_fails(): void
    {
        Push::fake();

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('smoke-signals');

        Push::app('broken');
    }
}
