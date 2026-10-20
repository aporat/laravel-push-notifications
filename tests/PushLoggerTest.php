<?php

declare(strict_types=1);

namespace Aporat\PushNotifications\Laravel\Tests;

use Aporat\PushNotifications\Laravel\Logging\PushLogger;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

final class PushLoggerTest extends TestCase
{
    private const string TOKEN = '00fc13adff785122b4ad28809a3420982341241421348097878e577c991de8f0';

    public function test_tokens_are_logged_in_full_by_default(): void
    {
        Log::shouldReceive('warning')->once()->with('APNs did not accept the notification.', ['target' => self::TOKEN, 'status' => 410]);

        (new PushLogger($this->app))->warning('APNs did not accept the notification.', ['target' => self::TOKEN, 'status' => 410]);
    }

    public function test_tokens_are_shortened_when_configured(): void
    {
        config()->set('push-notifications.logging.redact_tokens', true);
        Log::shouldReceive('debug')->once()->with('FCM accepted the message.', ['target' => '00fc13…e8f0', 'token' => '***', 'name' => 'projects/x/messages/1']);

        (new PushLogger($this->app))->debug('FCM accepted the message.', ['target' => self::TOKEN, 'token' => 'short', 'name' => 'projects/x/messages/1']);
    }

    public function test_named_channel_is_used(): void
    {
        config()->set('push-notifications.logging.channel', 'single');
        Log::shouldReceive('channel')->once()->with('single')->andReturn($channel = \Mockery::mock(LoggerInterface::class));
        $channel->shouldReceive('info')->once()->with('hello', []);

        (new PushLogger($this->app))->info('hello');
    }
}
