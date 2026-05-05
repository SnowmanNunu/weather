<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weather - SnowmanNunu</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <div class="container">
        <header>
            <div class="lang-switch">
                <button type="button" data-lang="zh" class="active">中文</button>
                <button type="button" data-lang="en">English</button>
            </div>
            <h1 id="pageTitle">🌤 天气查询</h1>
            <p class="subtitle" id="pageSubtitle">支持高德地图、和风天气等多数据源</p>
        </header>

        <form class="search-box" id="searchForm">
            <input
                type="text"
                id="cityInput"
                value="北京"
                placeholder="输入城市名称，如：北京、上海、深圳"
                required
            >
            <button type="submit" id="searchBtn">查询</button>
        </form>

        <div id="loading" class="loading hidden">正在查询天气…</div>
        <div id="error" class="alert alert-error hidden"></div>

        <div id="result"></div>

        <footer>
            <p>Powered by <a href="https://github.com/SnowmanNunu/weather" target="_blank">SnowmanNunu/Weather</a></p>
        </footer>
    </div>

    <script src="/assets/app.js"></script>
</body>
</html>
