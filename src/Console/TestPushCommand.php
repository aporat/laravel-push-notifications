<?php

declare(strict_types=1);

namespace Aporat\PushNotifications\Laravel\Console;

use Aporat\PushNotifications\Laravel\PushManager;
use Aporat\PushNotifications\Notification;
use Illuminate\Console\Command;

/**
 * Sends one notification to one device, for checking an app's credentials
 * and a device's token from the terminal.
 */
final class TestPushCommand extends Command
{
    protected $signature = 'push:test
        {app : The app name from push-notifications.apps}
        {token : The device token}
        {--title=Test notification : The title}
        {--body=Sent from push:test : The body}
        {--badge= : The badge count}
        {--data=* : Custom key=value pairs}';

    protected $description = 'Send a test push notification to one device';

    public function handle(PushManager $push): int
    {
        $app = $this->stringArgument('app');
        $token = $this->stringArgument('token');

        $notification = Notification::create($this->stringOption('title'), $this->stringOption('body'))
            ->sound('default')
            ->data($this->data());

        $badge = $this->option('badge');

        if (is_string($badge) && is_numeric($badge)) {
            $notification->badge((int) $badge);
        }

        $response = $push->app($app)->notify($notification, $token);

        $this->table(['Field', 'Value'], [
            ['app', $app],
            ['platform', $response->platform->value],
            ['target', $response->target],
            ['outcome', $response->outcome->value],
            ['status', (string) $response->statusCode],
            ['reason', $response->reason ?? '-'],
            ['id', $response->id ?? '-'],
            ['message', $response->message ?? '-'],
        ]);

        return $response->succeeded() ? self::SUCCESS : self::FAILURE;
    }

    private function stringArgument(string $name): string
    {
        $value = $this->argument($name);

        return is_string($value) ? $value : '';
    }

    private function stringOption(string $name): string
    {
        $value = $this->option($name);

        return is_string($value) ? $value : '';
    }

    /**
     * @return array<string, string>
     */
    private function data(): array
    {
        $data = [];
        $pairs = $this->option('data');

        foreach (is_array($pairs) ? $pairs : [] as $pair) {
            if (! is_string($pair) || ! str_contains($pair, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $pair, 2);
            $data[$key] = $value;
        }

        return $data;
    }
}
