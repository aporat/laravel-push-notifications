<?php

declare(strict_types=1);

namespace Aporat\PushNotifications\Laravel;

use Aporat\PushNotifications\Laravel\Console\TestPushCommand;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

class PushNotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/push-notifications.php', 'push-notifications');

        $this->app->singleton('push-notifications', fn (Container $app): PushManager => new PushManager($app));
        $this->app->alias('push-notifications', PushManager::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/push-notifications.php' => config_path('push-notifications.php'),
        ], 'config');

        if ($this->app->runningInConsole()) {
            $this->commands([TestPushCommand::class]);
        }
    }
}
