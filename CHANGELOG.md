# Changelog

## [v1.8.0] - 2026-05-04

### Added
- 多语言 i18n 支持（`zh` / `en`），SDK 与演示站点均可切换
- `Weather::setLang()` 链式方法，Provider 自动透传语言参数到上游 API
- `Provider::fetchAll()` + `Weather::getAll()` 并发批量查询，6 个接口并行发送
- Guzzle 自动重试中间件（网络超时 / 5xx / 429 指数退避，最多 2 次）
- 精细化异常体系：`InvalidKeyException`、`RateLimitException`
- GitHub Issue / PR 模板、Dependabot 自动依赖更新

### Changed
- PHPStan 从 Level 5 提升至 Level 8
- `.gitattributes` 优化 Composer 包体积（排除 CI / Docker / 测试）
- `public/.env.example` 补充 `WEATHER_LANG` 和 `QWEATHER_API_HOST`

## [v1.6.0] - 2026-05-04

### Added
- 和风天气（QWeather）专属 API Host 支持（`QWEATHER_API_HOST` 环境变量）
- 中文城市名自动转 LocationID（通过 QWeather GeoAPI）
- 新增 DTO：AirQuality、WeatherAlert、Precipitation、LifeIndex
- 新增接口：生活指数、空气质量、天气预警、分钟级降水预报
- 动态天气图标（根据天气文本自动匹配 Emoji）

### Fixed
- QWeather Provider HTTP Keep-Alive 连接复用导致超时
- QWeather 7 日预报 `week` 字段为空时从日期自动计算
- 修复 AMap 图标硬编码为太阳的问题

## [v1.1.0] - 2026-04-30

### Added
- 多 Provider 架构：支持高德地图、和风天气
- 标准化 DTO：CurrentWeather、Forecast、ForecastDay
- PSR-16 缓存支持
- Web 演示站点（SPA + IP 自动定位）
- PHPStan Level 5 + PHPCS PSR-12 CI

## [v1.0.0] - 2024-01-01

### Added
- 基于高德地图天气 API 的 PHP SDK
- PHPUnit 测试覆盖
- GitHub Actions CI
- Laravel ServiceProvider
