<?php

/*
 * This file is part of the snowmannunu/weather.
 *
 * (c) SnowmanNunu<345750542@qq.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace SnowmanNunu\Weather;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    protected $defer = true;

    public function register(): void
    {
        $this->app->singleton(Weather::class, function () {
            return new Weather(config('services.weather.key'));
        });

        $this->app->alias(Weather::class, 'weather');
    }

    public function provides(): array
    {
        return [Weather::class, 'weather'];
    }
}
