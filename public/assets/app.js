(function () {
  const $ = (sel) => document.querySelector(sel);

  const I18N = {
    zh: {
      title: '🌤 天气查询',
      subtitle: '支持高德地图、和风天气等多数据源',
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
      title: '🌤 Weather',
      subtitle: 'Multi-provider weather SDK demo',
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
    els.input.placeholder = t('placeholder');
    els.submitBtn.textContent = t('search');
    document.querySelectorAll('.lang-switch button').forEach((btn) => {
      btn.classList.toggle('active', btn.dataset.lang === currentLang);
    });
  }

  const els = {
    form: $('#searchForm'),
    input: $('#cityInput'),
    submitBtn: $('#searchBtn'),
    loading: $('#loading'),
    error: $('#error'),
    result: $('#result'),
  };

  function showLoading() {
    els.loading.textContent = t('loading');
    els.loading.classList.remove('hidden');
    els.error.classList.add('hidden');
    els.result.innerHTML = '';
  }

  function hideLoading() {
    els.loading.classList.add('hidden');
  }

  function showError(msg) {
    hideLoading();
    els.error.textContent = msg;
    els.error.classList.remove('hidden');
  }

  function renderWeather(data) {
    hideLoading();
    const current = data.current;
    const forecast = data.forecast;

    let html = '';

    if (data.alerts && data.alerts.length > 0) {
      data.alerts.forEach((alert) => {
        html += `<div class="alert alert-danger">
          <strong>${escapeHtml(alert.title)}</strong>
          <p>${escapeHtml(alert.content)}</p>
          <small>${escapeHtml(alert.pub_time)} · ${escapeHtml(alert.sender)}</small>
        </div>`;
      });
    }

    html += `<div class="card current">
      <div class="current-main">
        <div class="city-name">${escapeHtml(current.city)}</div>
        <div class="temperature">${current.temperature}°</div>
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
          <span class="meta-value">${escapeHtml(current.update_time)}</span>
        </div>
      </div>
    </div>`;

    if (data.minutely && data.minutely.length > 0) {
      html += `<h2 class="section-title">${t('minutelyTitle')}</h2>`;
      html += '<div class="card minutely">';
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
      html += `<h2 class="section-title">${t('forecastTitle')}</h2>`;
      html += '<div class="forecast-grid">';
      forecast.casts.forEach((cast) => {
        html += `<div class="card forecast">
          <div class="forecast-date">${escapeHtml(cast.date)}</div>
          <div class="forecast-day">${escapeHtml(cast.week)}</div>
          <div class="forecast-weather">${getWeatherIcon(cast.day_weather)} ${escapeHtml(cast.day_weather)}</div>
          <div class="forecast-temp">${cast.day_temp}° / ${cast.night_temp}°</div>
          <div class="forecast-wind">${escapeHtml(cast.day_wind)} ${escapeHtml(cast.day_power)}</div>
        </div>`;
      });
      html += '</div>';
    }

    if (data.aqi) {
      const aqi = data.aqi;
      const aqiColor = getAqiColor(aqi.aqi);
      html += `<div class="card aqi">
        <div class="aqi-header">
          <div class="aqi-value" style="color:${aqiColor}">${aqi.aqi != null ? aqi.aqi : '-'}</div>
          <div class="aqi-meta">
            <div class="aqi-category" style="color:${aqiColor}">${escapeHtml(aqi.category)}</div>
            <div class="aqi-label">${t('aqiLabel')}</div>
          </div>
        </div>
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
      html += `<h2 class="section-title">${t('indicesTitle')}</h2>`;
      html += '<div class="indices-grid">';
      data.indices.forEach((idx) => {
        html += `<div class="card index">
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
    const t = text.trim().toLowerCase();
    if (t.includes('晴') || t === 'sunny' || t === 'clear' || t.includes('fair')) return '☀️';
    if (t.includes('多云') || t.includes('cloudy')) return '⛅';
    if (t.includes('阴') || t.includes('overcast')) return '☁️';
    if (t.includes('雷阵雨') || t.includes('thunder')) return '⛈️';
    if (t.includes('暴雨') || t.includes('大暴雨') || t.includes('heavy rain') || t.includes('torrential')) return '🌧️';
    if (t.includes('雨') || t.includes('rain') || t.includes('drizzle') || t.includes('shower')) return '🌧️';
    if (t.includes('雪') || t.includes('snow') || t.includes('sleet') || t.includes('blizzard')) return '❄️';
    if (t.includes('雾') || t.includes('霾') || t.includes('fog') || t.includes('haze') || t.includes('mist')) return '🌫️';
    if (t.includes('风') || t.includes('沙') || t.includes('wind') || t.includes('sand') || t.includes('dust') || t.includes('blowing')) return '💨';
    if (t.includes('冰雹') || t.includes('hail')) return '🧊';
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

  document.querySelectorAll('.lang-switch button').forEach((btn) => {
    btn.addEventListener('click', function () {
      setLang(this.dataset.lang);
    });
  });

  updateStaticUI();
  detectLocation();
})();
