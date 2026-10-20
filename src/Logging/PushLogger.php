<?php

declare(strict_types=1);

namespace Aporat\PushNotifications\Laravel\Logging;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Log\LogManager;
use Psr\Log\LoggerInterface;
use Stringable;

/**
 * The logger the push clients write to: the channel named by
 * 'push-notifications.logging.channel', or the application's default.
 * The channel is looked up on every write, so a test that swaps the log
 * manager after boot (Log::spy()) still sees the entries, and each level
 * method forwards to the same-named method so a spy sees warning() as a
 * warning() call.
 */
final class PushLogger implements LoggerInterface
{
    public function __construct(
        private readonly Container $container,
    ) {}

    /** @param array<string, mixed> $context */
    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->channel()->emergency($message, $context);
    }

    /** @param array<string, mixed> $context */
    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->channel()->alert($message, $context);
    }

    /** @param array<string, mixed> $context */
    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->channel()->critical($message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string|Stringable $message, array $context = []): void
    {
        $this->channel()->error($message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->channel()->warning($message, $context);
    }

    /** @param array<string, mixed> $context */
    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->channel()->notice($message, $context);
    }

    /** @param array<string, mixed> $context */
    public function info(string|Stringable $message, array $context = []): void
    {
        $this->channel()->info($message, $context);
    }

    /** @param array<string, mixed> $context */
    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->channel()->debug($message, $context);
    }

    /** @param array<string, mixed> $context */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->channel()->log($level, $message, $context);
    }

    private function channel(): LoggerInterface
    {
        /** @var ConfigRepository $config */
        $config = $this->container->make('config');
        $channel = $config->get('push-notifications.logging.channel');

        /** @var LogManager $log */
        $log = $this->container->make('log');

        return is_string($channel) && $channel !== '' ? $log->channel($channel) : $log;
    }
}
