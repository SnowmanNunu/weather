(function () {
  const $ = (sel) => document.querySelector(sel);
  const $$ = (sel) => document.querySelectorAll(sel);

  const I18N = {
    zh: {
      title: '实时天气查询',
      subtitle: '聚合高德、和风天气等多数据源，精准预报',
      logo: 'Weather',
      placeholder: '输入城市名称，如：北京、上海、深圳',
      search: '查询',
      loading: '正在查询天气…',
      errorNetwork: '网络错误，请稍后重试',
      errorQuery: '查询失败',
      humidity: '湿度',
      windDir: '风向',
      windPower: '风力',
      updateTime: '更新时间',
      minutelyTitle: '分钟级降水预报',
      forecastTitle: '未来预报',
      aqiLabel: '空气质量指数',
      aqiPrimary: '首要污染物：',
      indicesTitle: '生活指数',
      poweredBy: 'Powered by',
    },
    en: {
      title: 'Live Weather',
      subtitle: 'Multi-provider weather SDK demo',
      logo: 'Weather',
      placeholder: 'Enter city name, e.g. Beijing, Shanghai',
      search: 'Search',
      loading: 'Loading weather…',
      errorNetwork: 'Network error, please try again later',
      errorQuery: 'Query failed',
      humidity: 'Humidity',
      windDir: 'Wind',
      windPower: 'Wind Scale',
      updateTime: 'Updated',
      minutelyTitle: 'Minutely Precipitation',
      forecastTitle: 'Forecast',
      aqiLabel: 'Air Quality Index',
      aqiPrimary: 'Primary Pollutant: ',
      indicesTitle: 'Life Indices',
      poweredBy: 'Powered by',
    },
  };

  let currentLang = localStorage.getItem('weather_lang') || 'zh';

  function t(key) {
    return I18N[currentLang]?.[key] ?? I18N['zh']?.[key] ?? key;
  }

  function setLang(lang) {
    currentLang = lang;
    localStorage.setItem('weather_lang', lang);
    document.documentElement.lang = lang === 'zh' ? 'zh-CN' : 'en';
    updateStaticUI();
    const city = els.input.value.trim();
    if (city) fetchWeather(city);
  }

  function updateStaticUI() {
    $('#pageTitle').textContent = t('title');
    $('#pageSubtitle').textContent = t('subtitle');
    $('#logoText').textContent = t('logo');
    els.input.placeholder = t('placeholder');
    els.btnText.textContent = t('search');
    $$('.lang-switch button').forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.lang === currentLang);
    });
    $$('.footer a').forEach((a) => {
      const p = a.closest('p');
      if (p) p.firstChild.textContent = t('poweredBy') + ' ';
    });
  }

  const els = {
    form: $('#searchForm'),
    input: $('#cityInput'),
    btnText: $('.btn-text'),
    btnLoader: $('.btn-loader'),
    loading: $('#loading'),
    loadingText: $('#loadingText'),
    error: $('#error'),
    result: $('#result'),
    bgLayer: $('#bgLayer'),
  };

  function showLoading() {
    els.btnText.classList.add('hidden');
    els.btnLoader.classList.remove('hidden');
    els.loadingText.textContent = t('loading');
    els.loading.classList.remove('hidden');
    els.error.classList.add('hidden');
    els.result.innerHTML = '';
  }

  function hideLoading() {
    els.btnText.classList.remove('hidden');
    els.btnLoader.classList.add('hidden');
    els.loading.classList.add('hidden');
  }

  function showError(msg) {
    hideLoading();
    els.error.textContent = msg;
    els.error.classList.remove('hidden');
  }

  function setBgByWeather(text) {
    if (!text) return;
    const w = text.trim().toLowerCase();
    const layer = els.bgLayer;
    layer.classList.remove('rain', 'snow', 'sunny');
    if (w.includes('雨') || w.includes('rain') || w.includes('thunder') || w.includes('shower')) {
      layer.classList.add('rain');
    } else if (w.includes('雪') || w.includes('snow') || w.includes('sleet')) {
      layer.classList.add('snow');
    } else if (w.includes('晴') || w === 'sunny' || w === 'clear' || w.includes('fair')) {
      layer.classList.add('sunny');
    }
  }

  function staggerClass(index) {
    const n = Math.min(index + 1, 7);
    return 'stagger-' + n;
  }

  function renderWeather(data) {
    hideLoading();
    const current = data.current;
    const forecast = data.forecast;

    let html = '';
    let cardIndex = 0;

    if (data.alerts && data.alerts.length > 0) {
      data.alerts.forEach((alert) => {
        html += `<div class="alert alert-danger ${staggerClass(cardIndex++)}">
          <strong>${escapeHtml(alert.title)}</strong>
          <p>${escapeHtml(alert.content)}</p>
          <small>${formatBeijingTime(alert.pub_time)} · ${escapeHtml(alert.sender)}</small>
        </div>`;
      });
    }

    if (current) {
      setBgByWeather(current.weather);
      const bigIcon = getWeatherIcon(current.weather);
      html += `<div class="card current ${staggerClass(cardIndex++)}">
        <div class="current-main">
          <div class="city-name">
            <span class="weather-icon-big">${bigIcon}</span>
            ${escapeHtml(current.city)}
          </div>
          <div class="temperature">${current.temperature != null ? current.temperature : '-'}°</div>
          <div class="weather-desc">${escapeHtml(current.weather)}</div>
        </div>
        <div class="current-meta">
          <div class="meta-item">
            <span class="meta-label">${t('humidity')}</span>
            <span class="meta-value">${current.humidity != null ? current.humidity + '%' : '-'}</span>
          </div>
          <div class="meta-item">
            <span class="meta-label">${t('windDir')}</span>
            <span class="meta-value">${escapeHtml(current.wind_direction)}</span>
          </div>
          <div class="meta-item">
            <span class="meta-label">${t('windPower')}</span>
            <span class="meta-value">${escapeHtml(current.wind_power)}</span>
          </div>
          <div class="meta-item">
            <span class="meta-label">${t('updateTime')}</span>
            <span class="meta-value">${formatBeijingTime(current.update_time)}</span>
          </div>
        </div>
      </div>`;
    }

    if (data.minutely && data.minutely.length > 0) {
      html += `<h2 class="section-title ${staggerClass(cardIndex)}">${t('minutelyTitle')}</h2>`;
      html += `<div class="card minutely ${staggerClass(cardIndex++)}">`;
      html += '<div class="minutely-chart">';
      const maxPrecip = Math.max(...data.minutely.map((m) => m.precipitation), 0.1);
      data.minutely.forEach((m, i) => {
        const heightPct = Math.min((m.precipitation / maxPrecip) * 100, 100);
        const barColor = m.precipitation > 0.5 ? '#3b82f6' : '#93c5fd';
        const timeLabel = m.time ? m.time.slice(11, 16) : '';
        html += `<div class="minutely-bar-wrap" title="${timeLabel} ${m.precipitation}mm">
          <div class="minutely-bar" style="height:${heightPct}%;background:${barColor}"></div>
          ${i % 10 === 0 ? `<div class="minutely-time">${timeLabel}</div>` : ''}
        </div>`;
      });
      html += '</div></div>';
    }

    if (forecast && forecast.casts && forecast.casts.length > 0) {
      html += `<h2 class="section-title ${staggerClass(cardIndex)}">${t('forecastTitle')}</h2>`;
      html += `<div class="forecast-grid">`;
      forecast.casts.forEach((cast, i) => {
        html += `<div class="card forecast ${staggerClass(cardIndex + i)}">
          <div class="forecast-date">${escapeHtml(cast.date)}</div>
          <div class="forecast-day">${escapeHtml(cast.week)}</div>
          <div class="forecast-weather">${getWeatherIcon(cast.day_weather)}</div>
          <div class="forecast-temp">${cast.day_temp}° / ${cast.night_temp}°</div>
          <div class="forecast-wind">${escapeHtml(cast.day_wind)} ${escapeHtml(cast.day_power)}</div>
        </div>`;
      });
      html += '</div>';
      cardIndex += forecast.casts.length;
    }

    if (data.aqi) {
      const aqi = data.aqi;
      const aqiColor = getAqiColor(aqi.aqi);
      const aqiPct = Math.min((aqi.aqi || 0) / 300 * 100, 100);
      html += `<div class="card aqi ${staggerClass(cardIndex++)}">
        <div class="aqi-header">
          <div class="aqi-value" style="color:${aqiColor}">${aqi.aqi != null ? aqi.aqi : '-'}</div>
          <div class="aqi-meta">
            <div class="aqi-category" style="color:${aqiColor}">${escapeHtml(aqi.category)}</div>
            <div class="aqi-label">${t('aqiLabel')}</div>
          </div>
        </div>
        <div class="aqi-bar"><div class="aqi-bar-fill" style="width:${aqiPct}%;background:${aqiColor}"></div></div>
        <div class="aqi-details">
          <div class="aqi-item"><span class="aqi-dt">PM2.5</span><span class="aqi-dv">${aqi.pm25 != null ? aqi.pm25 : '-'}</span></div>
          <div class="aqi-item"><span class="aqi-dt">PM10</span><span class="aqi-dv">${aqi.pm10 != null ? aqi.pm10 : '-'}</span></div>
          <div class="aqi-item"><span class="aqi-dt">NO₂</span><span class="aqi-dv">${aqi.no2 != null ? aqi.no2 : '-'}</span></div>
          <div class="aqi-item"><span class="aqi-dt">SO₂</span><span class="aqi-dv">${aqi.so2 != null ? aqi.so2 : '-'}</span></div>
          <div class="aqi-item"><span class="aqi-dt">CO</span><span class="aqi-dv">${aqi.co != null ? aqi.co : '-'}</span></div>
          <div class="aqi-item"><span class="aqi-dt">O₃</span><span class="aqi-dv">${aqi.o3 != null ? aqi.o3 : '-'}</span></div>
        </div>
        ${aqi.primary_pollutant ? `<div class="aqi-primary">${t('aqiPrimary')}${escapeHtml(aqi.primary_pollutant)}</div>` : ''}
      </div>`;
    }

    if (data.indices && data.indices.length > 0) {
      html += `<h2 class="section-title ${staggerClass(cardIndex)}">${t('indicesTitle')}</h2>`;
      html += `<div class="indices-grid">`;
      data.indices.forEach((idx, i) => {
        html += `<div class="card index ${staggerClass(cardIndex + i)}">
          <div class="index-name">${escapeHtml(idx.name)}</div>
          <div class="index-level">${escapeHtml(idx.category)}</div>
          <div class="index-advice">${escapeHtml(idx.advice)}</div>
        </div>`;
      });
      html += '</div>';
    }

    els.result.innerHTML = html;
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function formatBeijingTime(iso) {
    if (!iso) return '-';
    const d = new Date(iso);
    if (isNaN(d.getTime())) return escapeHtml(iso);
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
  }

  function getAqiColor(aqi) {
    if (aqi == null) return '#9ca3af';
    if (aqi <= 50) return '#22c55e';
    if (aqi <= 100) return '#eab308';
    if (aqi <= 150) return '#f97316';
    if (aqi <= 200) return '#ef4444';
    if (aqi <= 300) return '#a855f7';
    return '#7f1d1d';
  }

  function getWeatherIcon(text) {
    if (!text) return '☀️';
    const w = text.trim().toLowerCase();
    if (w.includes('晴') || w === 'sunny' || w === 'clear' || w.includes('fair')) return '☀️';
    if (w.includes('多云') || w.includes('cloudy')) return '⛅';
    if (w.includes('阴') || w.includes('overcast')) return '☁️';
    if (w.includes('雷阵雨') || w.includes('thunder')) return '⛈️';
    if (w.includes('暴雨') || w.includes('大暴雨') || w.includes('heavy rain') || w.includes('torrential')) return '🌧️';
    if (w.includes('雨') || w.includes('rain') || w.includes('drizzle') || w.includes('shower')) return '🌧️';
    if (w.includes('雪') || w.includes('snow') || w.includes('sleet') || w.includes('blizzard')) return '❄️';
    if (w.includes('雾') || w.includes('霾') || w.includes('fog') || w.includes('haze') || w.includes('mist')) return '🌫️';
    if (w.includes('风') || w.includes('沙') || w.includes('wind') || w.includes('sand') || w.includes('dust') || w.includes('blowing')) return '💨';
    if (w.includes('冰雹') || w.includes('hail')) return '🧊';
    return '☀️';
  }

  async function fetchWeather(city) {
    showLoading();
    try {
      const res = await fetch('/api.php?city=' + encodeURIComponent(city) + '&lang=' + encodeURIComponent(currentLang));
      const data = await res.json();
      if (!res.ok || data.error) {
        showError(data.error || t('errorQuery'));
        return;
      }
      renderWeather(data);
    } catch (e) {
      showError(t('errorNetwork'));
    }
  }

  async function detectLocation() {
    try {
      const res = await fetch('/locate.php');
      const data = await res.json();
      if (data.city) {
        els.input.value = data.city;
        await fetchWeather(data.city);
        return;
      }
    } catch (e) {
      // ignore
    }
    await fetchWeather(els.input.value);
  }

  els.form.addEventListener('submit', function (e) {
    e.preventDefault();
    const city = els.input.value.trim();
    if (!city) return;
    fetchWeather(city);
  });

  $$('.lang-switch button').forEach((btn) => {
    btn.addEventListener('click', function () {
      setLang(this.dataset.lang);
    });
  });

  $$('#quickChips button').forEach((btn) => {
    btn.addEventListener('click', function () {
      els.input.value = this.dataset.city;
      fetchWeather(this.dataset.city);
    });
  });

  updateStaticUI();
  detectLocation();
})();
