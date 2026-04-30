<?php

declare(strict_types=1);

namespace SnowmanNunu\Weather;

use GuzzleHttp\Client;
use SnowmanNunu\Weather\Contracts\Provider;
use SnowmanNunu\Weather\DTO\CurrentWeather;
use SnowmanNunu\Weather\DTO\Forecast;
use SnowmanNunu\Weather\Exceptions\InvalidArgumentException;
use SnowmanNunu\Weather\Providers\AMapProvider;

class Weather
{
    protected Provider $provider;

    protected ?\Psr\SimpleCache\CacheInterface $cache = null;

    protected int $cacheTtl = 300;

    /**
     * @param string|Provider $keyOrProvider API key string or a Provider instance
     */
    public function __construct($keyOrProvider)
    {
        if ($keyOrProvider instanceof Provider) {
            $this->provider = $keyOrProvider;
        } elseif (is_string($keyOrProvider) && !empty($keyOrProvider)) {
            $this->provider = new AMapProvider($keyOrProvider);
        } else {
            throw new InvalidArgumentException('API key must be a non-empty string or a Provider instance.');
        }
    }

    public function getProvider(): Provider
    {
        return $this->provider;
    }

    public function setProvider(Provider $provider): self
    {
        $this->provider = $provider;

        return $this;
    }

    public function withCache(\Psr\SimpleCache\CacheInterface $cache, int $ttl = 300): self
    {
        $this->cache = $cache;
        $this->cacheTtl = $ttl;

        return $this;
    }

    public function getName(): string
    {
        return $this->provider->getName();
    }

    public function getLiveWeather(string $city): CurrentWeather
    {
        $cacheKey = sprintf('weather:%s:%s:live', $this->getName(), md5($city));

        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->provider->getLiveWeather($city);
        $this->setToCache($cacheKey, $result);

        return $result;
    }

    public function getForecastsWeather(string $city): Forecast
    {
        $cacheKey = sprintf('weather:%s:%s:forecast', $this->getName(), md5($city));

        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->provider->getForecastsWeather($city);
        $this->setToCache($cacheKey, $result);

        return $result;
    }

    /**
     * @return \SnowmanNunu\Weather\DTO\LifeIndex[]
     */
    public function getLifeIndices(string $city): array
    {
        $cacheKey = sprintf('weather:%s:%s:indices', $this->getName(), md5($city));

        try {
            $cached = $this->cache?->get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
            // ignore
        }

        $result = $this->provider->getLifeIndices($city);

        try {
            $this->cache?->set($cacheKey, $result, $this->cacheTtl);
        } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
            // ignore
        }

        return $result;
    }

    /**
     * Backward compatibility. Returns raw array representation.
     *
     * @return array<string, mixed>
     */
    public function getWeather(string $city, string $type = 'base'): array
    {
        return $type === 'all'
            ? $this->getForecastsWeather($city)->toArray()
            : $this->getLiveWeather($city)->toArray();
    }

    protected function getFromCache(string $key): ?object
    {
        if ($this->cache === null) {
            return null;
        }

        try {
            $cached = $this->cache->get($key);
            if ($cached instanceof CurrentWeather || $cached instanceof Forecast) {
                return $cached;
            }
        } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
            // ignore cache read errors
        }

        return null;
    }

    protected function setToCache(string $key, object $value): void
    {
        if ($this->cache === null) {
            return;
        }

        try {
            $this->cache->set($key, $value, $this->cacheTtl);
        } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
            // ignore cache write errors
        }
    }

    /**
     * Backward compatibility: proxy to provider's HTTP client methods.
     */
    public function getHttpClient(): Client
    {
        if ($this->provider instanceof AMapProvider) {
            return $this->provider->getHttpClient();
        }

        throw new \BadMethodCallException('getHttpClient() is only available when using AMapProvider.');
    }

    public function setHttpClient(Client $client): void
    {
        if ($this->provider instanceof AMapProvider) {
            $this->provider->setHttpClient($client);

            return;
        }

        throw new \BadMethodCallException('setHttpClient() is only available when using AMapProvider.');
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setGuzzleOptions(array $options): void
    {
        if ($this->provider instanceof AMapProvider) {
            $this->provider->setGuzzleOptions($options);

            return;
        }

        throw new \BadMethodCallException('setGuzzleOptions() is only available when using AMapProvider.');
    }
}
