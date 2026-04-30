<?php

declare(strict_types=1);

namespace SnowmanNunu\Weather;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use SnowmanNunu\Weather\Contracts\Provider;
use SnowmanNunu\Weather\Exceptions\HttpException;
use SnowmanNunu\Weather\Exceptions\InvalidArgumentException;
use SnowmanNunu\Weather\Providers\AMapProvider;

class Weather implements Provider
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

    public function getLiveWeather(string $city, string $format = 'json')
    {
        return $this->getWeather($city, 'base', $format);
    }

    public function getForecastsWeather(string $city, string $format = 'json')
    {
        return $this->getWeather($city, 'all', $format);
    }

    public function getWeather(string $city, string $type = 'base', string $format = 'json')
    {
        $cacheKey = sprintf('weather:%s:%s:%s:%s', $this->getName(), md5($city), $type, $format);

        if ($this->cache !== null) {
            try {
                $cached = $this->cache->get($cacheKey);
                if ($cached !== null) {
                    return $cached;
                }
            } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
                // ignore cache read errors
            }
        }

        $result = $this->provider->getWeather($city, $type, $format);

        if ($this->cache !== null) {
            try {
                $this->cache->set($cacheKey, $result, $this->cacheTtl);
            } catch (\Psr\SimpleCache\InvalidArgumentException $e) {
                // ignore cache write errors
            }
        }

        return $result;
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

    public function setGuzzleOptions(array $options): void
    {
        if ($this->provider instanceof AMapProvider) {
            $this->provider->setGuzzleOptions($options);

            return;
        }

        throw new \BadMethodCallException('setGuzzleOptions() is only available when using AMapProvider.');
    }
}
