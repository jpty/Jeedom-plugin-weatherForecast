/* ===============================
   WeatherForecast – Rain widget
   =============================== */

window.WFRain = (function () {
  'use strict';

  if (window.__WFRainLoaded) {
    return window.WFRain;
  }
  window.__WFRainLoaded = true;

  const SVG_NS = 'http://www.w3.org/2000/svg';

  /* ---------------------------
     Utils
  --------------------------- */

  function parseForecast(forecast) {
    const intervals = [];

    forecast.forEach((item, i) => {
      const end = new Date(item.time);
      const start = i === 0
        ? new Date(end.getTime() - 5 * 60000)
        : new Date(forecast[i - 1].time);

      intervals.push({
        start,
        end,
        minutes: (end - start) / 60000,
        intensity: item.rain_intensity,
        desc: item.rain_intensity_description
      });
    });

    return intervals;
  }

  function groupByIntensity(intervals) {
    const groups = [];
    let current = null;

    intervals.forEach(intv => {
      if (!current || current.intensity !== intv.intensity) {
        current = { ...intv };
        groups.push(current);
      } else {
        current.end = intv.end;
        current.minutes += intv.minutes;
      }
    });

    return groups;
  }

  function formatTime(date) {
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }

  /* ---------------------------
     SVG
  --------------------------- */

  function createCloudSVG(intensity, withDrops) {
    const svg = document.createElementNS(SVG_NS, 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');

    const g = document.createElementNS(SVG_NS, 'g');
    const cloud = document.createElementNS(SVG_NS, 'path');
    cloud.setAttribute( 'd', 'M6 14h11a4 4 0 0 0 0-8 5 5 0 0 0-9-1 4 4 0 0 0-2 9z');
    if (intensity > 1) {
      cloud.setAttribute('fill', 'currentColor');
    } else {
      cloud.setAttribute('fill', 'none');
      cloud.setAttribute('stroke', 'currentColor');
    }
    g.appendChild(cloud);

    if (withDrops && intensity > 1) {
      const count = Math.max(0, intensity - 1);
      const centerX = 12;
      var spacing = 6;
      if(intensity > 4) spacing = 3;
      const startX = centerX - ((count - 1) * spacing) / 2;

      for (let i = 0; i < count; i++) {
        const x = startX + i * spacing;
        const drop = document.createElementNS(SVG_NS, 'path');
        drop.setAttribute(
          'd',
          `M${x} 8
           C${x-1.5} 12 ${x-1.5} 14 ${x} 16
           C${x+1.5} 14 ${x+1.5} 12 ${x} 8 Z`
        );
        drop.setAttribute('class', 'drop');
        drop.style.animationDelay = `${i * 0.2}s`;
        g.appendChild(drop);
      }
    }
    g.setAttribute("transform", "translate(0,-4)");
    svg.appendChild(g);
    return svg;
  }

  /* ---------------------------
     Render principal
  --------------------------- */
  function render(widget, forecast, options = {}) {
    const {
      useIconFiles = false,
      withAnimatedDrops = true,
      hideIfDry = false,
      testWidget = false
    } = options;

    const table = widget.querySelector('.WF-rain-table');
    const timeRow = table.querySelector('.time-row');
    const iconRow = table.querySelector('.icon-row');
    timeRow.innerHTML = '';
    iconRow.innerHTML = '';

    if(testWidget) { // Build forecast from current time for widget test
      const now = new Date();
      const step = 60 * 1000;
      let currentTime = new Date(Math.floor(now.getTime() / step) * step);
      forecast = [];
      const periods = [ { duration: 5, intensity: 1 }, { duration: 5, intensity: 2 },
        { duration: 5, intensity: 3 }, { duration: 5, intensity: 4 }, { duration: 5, intensity: 1 },
        { duration: 5, intensity: 2 }, { duration: 10, intensity: 3 },
        { duration: 10, intensity: 3 }, { duration: 10, intensity: 4 }
      ];
      const intensityDescriptions = { 1: "Temps sec", 2: "Pluie fine",
        3: "Pluie modérée", 4: "Pluie forte", 5: "Pluie diluvienne" };
      periods.forEach(period => {
        currentTime = new Date(currentTime.getTime() + period.duration * 60 * 1000);
        forecast.push({
          time: currentTime.toISOString(),
          rain_intensity: period.intensity,
          rain_intensity_description:
            intensityDescriptions[period.intensity]
        });
      });
    }
    if (!forecast || !forecast.length) {
      widget.style.display = 'none';
      return;
    }
    const intervals = parseForecast(forecast);
    const groups = groupByIntensity(intervals);
    if ( hideIfDry && groups.length === 1 && groups[0].intensity === 1) {
      widget.style.display = 'none';
      return;
    }

    widget.style.display = '';

    const totalMinutes = intervals.reduce((s, i) => s + i.minutes, 0);

    groups.forEach((group, index) => {
      const width = (group.minutes / totalMinutes * 100) + '%';
      const startLabel = formatTime(group.start);
      const endLabel = formatTime(group.end);

      /* --- TH --- */
      const th = document.createElement('th');
      th.style.width = width;
      th.classList.add(`intensity-${group.intensity}`);
      const timeDiv = document.createElement('div');
      timeDiv.className = 'time-range';
      const startSpan = document.createElement('span');
      startSpan.textContent = startLabel;
      timeDiv.appendChild(startSpan);
      if (index === groups.length - 1) {
        const endSpan = document.createElement('span');
        endSpan.textContent = endLabel;
        timeDiv.appendChild(endSpan);
      }
      th.appendChild(timeDiv);
      timeRow.appendChild(th);

      /* --- TD --- */
      const td = document.createElement('td');
      td.style.width = width;
      td.classList.add(`intensity-${group.intensity}`);
      td.title = `${group.desc} ${startLabel} → ${endLabel}`;
      if (useIconFiles) {
        const img = document.createElement('img');
        img.src = `plugins/weatherForecast/core/template/images/Rain${group.intensity}.svg`;
        img.alt = group.desc;
        td.appendChild(img);
      } else {
        const svg = createCloudSVG(group.intensity, withAnimatedDrops);
        td.appendChild(svg);
      }
      iconRow.appendChild(td);
    });
  }

  return {
    render
  };
})();
