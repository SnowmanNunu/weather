<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weather — SnowmanNunu</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <div class="bg-layer" id="bgLayer"></div>

    <div class="page">
        <header class="topbar">
            <div class="logo">🌤 <span id="logoText">Weather</span></div>
            <div class="lang-switch" id="langSwitch">
                <button type="button" data-lang="zh" class="active">中文</button>
                <button type="button" data-lang="en">EN</button>
            </div>
        </header>

        <section class="hero">
            <h1 id="pageTitle">实时天气查询</h1>
            <p class="lead" id="pageSubtitle">聚合高德、和风天气等多数据源，精准预报</p>

            <form class="search" id="searchForm">
                <div class="search-inner">
                    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.3-4.3"></path>
                    </svg>
                    <input type="text" id="cityInput" value="北京" placeholder="输入城市名称，如：北京、上海、深圳" autocomplete="off">
                    <button type="submit" id="searchBtn">
                        <span class="btn-text">查询</span>
                        <span class="btn-loader hidden"></span>
                    </button>
                </div>
                <div class="chips" id="quickChips">
                    <button type="button" data-city="北京">北京</button>
                    <button type="button" data-city="上海">上海</button>
                    <button type="button" data-city="广州">广州</button>
                    <button type="button" data-city="深圳">深圳</button>
                    <button type="button" data-city="杭州">杭州</button>
                    <button type="button" data-city="成都">成都</button>
                </div>
            </form>
        </section>

        <div id="loading" class="loading hidden">
            <div class="spinner"></div>
            <p id="loadingText">正在查询天气…</p>
        </div>

        <div id="error" class="alert alert-error hidden"></div>

        <main id="result"></main>

        <footer class="footer">
            <p>Powered by <a href="https://github.com/SnowmanNunu/weather" target="_blank" rel="noopener">SnowmanNunu/Weather</a></p>
        </footer>
    </div>

    <script src="/assets/app.js"></script>
</body>
</html>
