<?php

declare(strict_types=1);

namespace Aporat\PushNotifications\Laravel\Testing;

use Aporat\PushNotifications\Contracts\Message;
use Aporat\PushNotifications\Laravel\AppSender;
use Aporat\PushNotifications\Laravel\PushManager;
use Aporat\PushNotifications\Outcome;
use Aporat\PushNotifications\Platform;
use Aporat\PushNotifications\Response;
use Aporat\PushNotifications\Testing\FakeSender;
use Closure;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * The manager with every app's client replaced by a recording fake. Apps
 * still come from config, so they render and raise events exactly as in
 * production; only the HTTP leaves.
 */
class PushFake extends PushManager
{
    /** @var array<string, FakeSender> */
    protected array $fakes = [];

    /** @var Outcome|Closure(Message): (Response|Outcome) */
    protected Outcome|Closure $responder = Outcome::Delivered;

    /**
     * What every fake answers from now on: a fixed outcome, or a callback
     * deciding per message.
     *
     * @param  Outcome|Closure(Message): (Response|Outcome)  $respondWith
     */
    public function respondWith(Outcome|Closure $respondWith): static
    {
        $this->responder = $respondWith;

        foreach ($this->fakes as $fake) {
            $fake->respondWith($respondWith);
        }

        return $this;
    }

    /**
     * Every message sent, across all apps or for one, in order.
     *
     * @return list<Message>
     */
    public function sent(?string $app = null): array
    {
        if ($app !== null) {
            return isset($this->fakes[$app]) ? $this->fakes[$app]->sent() : [];
        }

        return array_merge(...array_values(array_map(static fn (FakeSender $fake): array => $fake->sent(), $this->fakes)));
    }

    /**
     * @param  Closure(Message): bool|null  $callback
     */
    public function assertSent(string $app, ?Closure $callback = null): static
    {
        $matching = $this->matching($app, $callback);

        PHPUnit::assertNotEmpty($matching, sprintf('No push was sent through app "%s"%s.', $app, $callback === null ? '' : ' matching the callback'));

        return $this;
    }

    /**
     * @param  Closure(Message): bool|null  $callback
     */
    public function assertNotSent(string $app, ?Closure $callback = null): static
    {
        $matching = $this->matching($app, $callback);

        PHPUnit::assertEmpty($matching, sprintf('%d unexpected push(es) sent through app "%s".', count($matching), $app));

        return $this;
    }

    public function assertSentTo(string $app, string $target): static
    {
        return $this->assertSent($app, static fn (Message $message): bool => $message->target() === $target);
    }

    public function assertSentCount(int $count, ?string $app = null): static
    {
        PHPUnit::assertCount($count, $this->sent($app), sprintf('Expected %d push(es)%s.', $count, $app === null ? '' : ' through app "'.$app.'"'));

        return $this;
    }

    public function assertNothingSent(): static
    {
        PHPUnit::assertEmpty($this->sent(), sprintf('%d push(es) were sent.', count($this->sent())));

        return $this;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function buildAppSender(string $name, array $config): AppSender
    {
        $platform = match ($config['driver'] ?? null) {
            'apns' => Platform::Apns,
            'fcm' => Platform::Fcm,
            default => parent::buildAppSender($name, $config)->platform, // throws the real error
        };

        $fake = $this->fakes[$name] ??= new FakeSender($this->responder);
        $bundleId = $config['bundle_id'] ?? null;

        return new AppSender(
            name: $name,
            platform: $platform,
            client: $fake,
            container: $this->container,
            bundleId: is_string($bundleId) ? $bundleId : null,
        );
    }

    /**
     * @param  Closure(Message): bool|null  $callback
     * @return list<Message>
     */
    private function matching(string $app, ?Closure $callback): array
    {
        $sent = $this->sent($app);

        return $callback === null ? $sent : array_values(array_filter($sent, $callback));
    }
}
