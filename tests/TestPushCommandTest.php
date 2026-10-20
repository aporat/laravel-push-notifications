<?php

declare(strict_types=1);

namespace Aporat\PushNotifications\Laravel\Tests;

use Aporat\PushNotifications\Apns\Message as ApnsMessage;
use Aporat\PushNotifications\Laravel\Facades\Push;
use Aporat\PushNotifications\Outcome;

final class TestPushCommandTest extends TestCase
{
    public function test_sends_a_notification_and_reports_the_answer(): void
    {
        $fake = Push::fake();

        $this->artisan('push:test', ['app' => 'ios', 'token' => 'ab12', '--title' => 'Hi', '--body' => 'There', '--badge' => '3', '--data' => ['k=v', 'bad']])
            ->expectsOutputToContain('delivered')
            ->assertSuccessful();

        $fake->assertSentTo('ios', 'ab12');
        $message = $fake->sent('ios')[0];
        self::assertInstanceOf(ApnsMessage::class, $message);
        self::assertSame(['title' => 'Hi', 'body' => 'There'], json_decode($message->body(), true)['aps']['alert']);
        self::assertSame(3, json_decode($message->body(), true)['aps']['badge']);
        self::assertSame('v', json_decode($message->body(), true)['k']);
    }

    public function test_fails_when_the_push_is_refused(): void
    {
        Push::fake()->respondWith(Outcome::Unregistered);

        $this->artisan('push:test', ['app' => 'ios', 'token' => 'dead'])
            ->expectsOutputToContain('unregistered')
            ->assertFailed();
    }
}
