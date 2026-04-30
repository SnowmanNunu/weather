(function () {
  const $ = (sel) => document.querySelector(sel);

  const els = {
    form: $('#searchForm'),
    input: $('#cityInput'),
    loading: $('#loading'),
    error: $('#error'),
    result: $('#result'),
  };

  function showLoading() {
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

    html += `<div class="card current">
      <div class="current-main">
        <div class="city-name">${escapeHtml(current.city)}</div>
        <div class="temperature">${current.temperature}°</div>
        <div class="weather-desc">${escapeHtml(current.weather)}</div>
      </div>
      <div class="current-meta">
        <div class="meta-item">
          <span class="meta-label">湿度</span>
          <span class="meta-value">${current.humidity != null ? current.humidity + '%' : '-'}</span>
        </div>
        <div class="meta-item">
          <span class="meta-label">风向</span>
          <span class="meta-value">${escapeHtml(current.wind_direction)}</span>
        </div>
        <div class="meta-item">
          <span class="meta-label">风力</span>
          <span class="meta-value">${escapeHtml(current.wind_power)}</span>
        </div>
        <div class="meta-item">
          <span class="meta-label">更新时间</span>
          <span class="meta-value">${escapeHtml(current.update_time)}</span>
        </div>
      </div>
    </div>`;

    if (forecast && forecast.casts && forecast.casts.length > 0) {
      html += '<h2 class="section-title">未来预报</h2>';
      html += '<div class="forecast-grid">';
      forecast.casts.forEach((cast) => {
        html += `<div class="card forecast">
          <div class="forecast-date">${escapeHtml(cast.date)}</div>
          <div class="forecast-day">${escapeHtml(cast.week)}</div>
          <div class="forecast-weather">☀ ${escapeHtml(cast.day_weather)}</div>
          <div class="forecast-temp">${cast.day_temp}° / ${cast.night_temp}°</div>
          <div class="forecast-wind">${escapeHtml(cast.day_wind)}风 ${escapeHtml(cast.day_power)}</div>
        </div>`;
      });
      html += '</div>';
    }

    if (data.indices && data.indices.length > 0) {
      html += '<h2 class="section-title">生活指数</h2>';
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

  async function fetchWeather(city) {
    showLoading();
    try {
      const res = await fetch('/api.php?city=' + encodeURIComponent(city));
      const data = await res.json();
      if (!res.ok || data.error) {
        showError(data.error || '查询失败');
        return;
      }
      renderWeather(data);
    } catch (e) {
      showError('网络错误，请稍后重试');
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

  detectLocation();
})();
