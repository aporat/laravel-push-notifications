<?php

declare(strict_types=1);

namespace Aporat\PushNotifications\Laravel;

use Aporat\PushNotifications\Apns\Client as ApnsClient;
use Aporat\PushNotifications\Apns\Credentials;
use Aporat\PushNotifications\Apns\Environment;
use Aporat\PushNotifications\Contracts\Sender;
use Aporat\PushNotifications\Exceptions\ConfigurationException;
use Aporat\PushNotifications\Fcm\Client as FcmClient;
use Aporat\PushNotifications\Fcm\ServiceAccount;
use Aporat\PushNotifications\Laravel\Logging\PushLogger;
use Aporat\PushNotifications\Laravel\Support\Psr6\LaravelCacheItemPool;
use Aporat\PushNotifications\Platform;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * Resolves the configured apps into senders, and the clients behind them.
 *
 * APNs clients are shared per team and environment, since one auth key
 * signs for every app in the team and Apple binds a connection to the
 * team. FCM clients are one per app, since each app has its own Firebase
 * project. All of them live as long as this manager, so a queue worker
 * keeps its connections and tokens between jobs.
 */
class PushManager
{
    /** @var array<string, AppSender> */
    protected array $apps = [];

    /** @var array<string, ApnsClient> */
    protected array $apnsClients = [];

    /** @var array<string, FcmClient> */
    protected array $fcmClients = [];

    public function __construct(
        protected Container $container,
    ) {}

    /**
     * The sender for one configured app.
     *
     * @throws ConfigurationException
     */
    public function app(string $name): AppSender
    {
        return $this->apps[$name] ??= $this->buildAppSender($name, $this->appConfig($name));
    }

    /**
     * The names of every configured app.
     *
     * @return list<string>
     */
    public function apps(): array
    {
        $apps = $this->config('apps');

        return is_array($apps) ? array_map('strval', array_keys($apps)) : [];
    }

    /**
     * The APNs client for a team, on one environment.
     *
     * @throws ConfigurationException
     */
    public function apnsClient(string $team, Environment $environment): ApnsClient
    {
        $key = $team.'|'.$environment->value;

        if (isset($this->apnsClients[$key])) {
            return $this->apnsClients[$key];
        }

        $config = $this->config('teams.'.$team);

        if (! is_array($config)) {
            throw new ConfigurationException(sprintf('Push team "%s" is not configured under push-notifications.teams.', $team));
        }

        $credentials = new Credentials(
            teamId: $this->string($config, 'team_id', 'teams.'.$team),
            keyId: $this->string($config, 'key_id', 'teams.'.$team),
            privateKey: $this->string($config, 'private_key', 'teams.'.$team),
        );

        // A client for the other environment shares the provider token; a
        // key signs for both gateways.
        $sibling = $this->apnsClients[$team.'|'.($environment === Environment::Production ? Environment::Sandbox : Environment::Production)->value] ?? null;

        if ($sibling !== null) {
            return $this->apnsClients[$key] = $sibling->withEnvironment($environment);
        }

        $options = $this->httpOptions() + [
            'port' => $this->int('apns.port', 443),
            'token_refresh_after' => $this->int('apns.token_refresh_after', 3000),
        ];

        return $this->apnsClients[$key] = new ApnsClient(
            credentials: $credentials,
            environment: $environment,
            options: $options,
            cache: $this->tokenCache(),
            logger: $this->logger(),
        );
    }

    /**
     * The FCM client for an app's Firebase project.
     *
     * @throws ConfigurationException
     */
    public function fcmClient(string $app): FcmClient
    {
        if (isset($this->fcmClients[$app])) {
            return $this->fcmClients[$app];
        }

        $config = $this->appConfig($app);
        $serviceAccount = $this->string($config, 'service_account', 'apps.'.$app);

        $account = str_contains($serviceAccount, '{')
            ? ServiceAccount::fromJson($serviceAccount)
            : ServiceAccount::fromFile($serviceAccount);

        return $this->fcmClients[$app] = new FcmClient(
            account: $account,
            options: $this->httpOptions(),
            cache: $this->tokenCache(),
            logger: $this->logger(),
        );
    }

    /**
     * The PSR-6 pool the clients share their tokens through, or null when
     * the token cache is disabled.
     */
    public function tokenCache(): ?CacheItemPoolInterface
    {
        if (! (bool) ($this->config('token_cache.enabled') ?? true)) {
            return null;
        }

        $store = $this->config('token_cache.store');

        /** @var CacheFactory $cache */
        $cache = $this->container->make(CacheFactory::class);

        return new LaravelCacheItemPool($cache->store(is_string($store) && $store !== '' ? $store : null), 'push-notifications:');
    }

    public function logger(): LoggerInterface
    {
        return new PushLogger($this->container);
    }

    /**
     * @param  array<string, mixed>  $config
     *
     * @throws ConfigurationException
     */
    protected function buildAppSender(string $name, array $config): AppSender
    {
        $driver = $config['driver'] ?? null;

        [$platform, $client, $bundleId, $fallback] = match ($driver) {
            'apns' => $this->buildApns($name, $config),
            'fcm' => [Platform::Fcm, $this->fcmClient($name), null, null],
            default => throw new ConfigurationException(sprintf('Push app "%s" has driver "%s"; expected "apns" or "fcm".', $name, is_scalar($driver) ? (string) $driver : gettype($driver))),
        };

        return new AppSender(
            name: $name,
            platform: $platform,
            client: $client,
            events: $this->container->make(Dispatcher::class),
            bundleId: $bundleId,
            sandboxFallback: $fallback,
        );
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array{0: Platform, 1: Sender, 2: string, 3: Sender|null}
     *
     * @throws ConfigurationException
     */
    protected function buildApns(string $name, array $config): array
    {
        $bundleId = $this->string($config, 'bundle_id', 'apps.'.$name);
        $team = $this->string($config, 'team', 'apps.'.$name);
        $environment = $this->environment($config['environment'] ?? $this->config('apns.environment'), $name);

        $client = $this->apnsClient($team, $environment);
        $fallback = null;

        if ($environment === Environment::Production && (bool) ($this->config('apns.sandbox_fallback') ?? false)) {
            $fallback = $this->apnsClient($team, Environment::Sandbox);
        }

        return [Platform::Apns, $client, $bundleId, $fallback];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConfigurationException
     */
    protected function appConfig(string $name): array
    {
        $config = $this->config('apps.'.$name);

        if (! is_array($config)) {
            throw new ConfigurationException(sprintf('Push app "%s" is not configured under push-notifications.apps.', $name));
        }

        /** @var array<string, mixed> $config */
        return $config;
    }

    /**
     * @return array{timeout: int, connect_timeout: int, concurrency: int, proxy?: string}
     */
    protected function httpOptions(): array
    {
        $options = [
            'timeout' => $this->int('http.timeout', 30),
            'connect_timeout' => $this->int('http.connect_timeout', 10),
            'concurrency' => $this->int('http.concurrency', 50),
        ];

        $proxy = $this->config('http.proxy');

        if (is_string($proxy) && $proxy !== '') {
            $options['proxy'] = $proxy;
        }

        return $options;
    }

    protected function int(string $key, int $default): int
    {
        $value = $this->config($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    protected function config(string $key): mixed
    {
        /** @var ConfigRepository $config */
        $config = $this->container->make('config');

        return $config->get('push-notifications.'.$key);
    }

    /**
     * @throws ConfigurationException
     */
    private function environment(mixed $value, string $app): Environment
    {
        if ($value instanceof Environment) {
            return $value;
        }

        $environment = is_string($value) ? Environment::tryFrom(strtolower($value)) : null;

        if ($environment === null) {
            throw new ConfigurationException(sprintf('Push app "%s" has an unknown APNs environment; expected "production" or "sandbox".', $app));
        }

        return $environment;
    }

    /**
     * @param  array<array-key, mixed>  $config
     *
     * @throws ConfigurationException
     */
    private function string(array $config, string $key, string $where): string
    {
        $value = $config[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new ConfigurationException(sprintf('push-notifications.%s.%s is missing.', $where, $key));
        }

        return $value;
    }
}
