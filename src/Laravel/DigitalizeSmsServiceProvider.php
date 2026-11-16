<?php

declare(strict_types=1);

namespace Digitalize\Sms\Laravel;

use Digitalize\Sms\Client;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

/** Auto-discovered by Laravel. Publish the config with: php artisan vendor:publish --tag=digitalize-sms-config */
final class DigitalizeSmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/digitalize-sms.php', 'digitalize-sms');

        $this->app->singleton(Client::class, function ($app): Client {
            $config = $app['config']['digitalize-sms'];

            if (empty($config['api_key'])) {
                throw new \RuntimeException('Set DIGITALIZE_SMS_KEY in .env to use the Digitalize SMS API.');
            }

            return new Client($config['api_key'], [
                'base_url' => $config['base_url'] ?? Client::DEFAULT_BASE_URL,
                'timeout' => $config['timeout'] ?? 15,
                'connect_timeout' => $config['connect_timeout'] ?? 5,
                'max_retries' => $config['max_retries'] ?? 2,
            ]);
        });
        $this->app->alias(Client::class, 'digitalize-sms');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/digitalize-sms.php' => $this->app->configPath('digitalize-sms.php'),
            ], 'digitalize-sms-config');
        }

        // Lets notifications use via() => ['digitalize-sms'] as well as the class name
        $this->callAfterResolving(ChannelManager::class, function (ChannelManager $manager): void {
            $manager->extend('digitalize-sms', fn ($app) => $app->make(DigitalizeSmsChannel::class));
        });
    }
}
