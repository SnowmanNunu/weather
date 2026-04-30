<?php

declare(strict_types=1);

namespace SnowmanNunu\Weather;

use Illuminate\Support\ServiceProvider as LaravelServiceProvider;
use SnowmanNunu\Weather\Contracts\Provider;
use SnowmanNunu\Weather\Providers\AMapProvider;

class ServiceProvider extends LaravelServiceProvider
{
    protected $defer = true;

    public function register(): void
    {
        $this->app->singleton(Weather::class, function ($app) {
            $config = $app['config']['services.weather'] ?? [];
            $key = $config['key'] ?? '';
            $driver = $config['driver'] ?? 'amap';

            $provider = match ($driver) {
                'qweather' => new \SnowmanNunu\Weather\Providers\QWeatherProvider($key),
                'openweathermap' => new \SnowmanNunu\Weather\Providers\OpenWeatherMapProvider($key),
                default => new AMapProvider($key),
            };

            $weather = new Weather($provider);

            if (!empty($config['cache']['store'])) {
                $cacheStore = $app['cache']->store($config['cache']['store']);
                $ttl = (int) ($config['cache']['ttl'] ?? 300);
                $weather->withCache($cacheStore, $ttl);
            }

            return $weather;
        });

        $this->app->alias(Weather::class, 'weather');
    }

    public function provides(): array
    {
        return [Weather::class, 'weather'];
    }
}
