/**
 * Minimal SVG charts for the analytics dashboard: line, bar and doughnut.
 *
 * Why hand-rolled rather than a library: CLAUDE.md constrains this project to
 * plain PHP and vanilla HTML/CSS/JS with no build step, and self-hosters should
 * not have to ship a charting bundle to see three panels. The previous
 * implementation called Chart.js from an inline <script>, but Chart.js was never
 * loaded — there was no <script src> for it anywhere and no vendored copy — so
 * the canvases had never rendered even before the CSP refused the inline block.
 *
 * SVG rather than <canvas> so the output is themeable and accessible: strokes
 * and fills read the same OKLCH tokens as the rest of the admin, which means the
 * charts follow light/dark automatically. The old config hardcoded a greyscale
 * ramp (#111111 through #c7c7c7) that was light-mode-only.
 *
 * Usage — the view hands data over as JSON in a data attribute, so no PHP is
 * ever interpolated into JavaScript:
 *
 *   <div data-chart="line"     data-series='[{"label":"1 Jul","value":10}]'></div>
 *   <div data-chart="bar"      data-series='[...]'></div>
 *   <div data-chart="doughnut" data-series='[...]'></div>
 *
 * Optional: data-value-prefix="£" formats axis and tooltip values.
 */

(function () {
  'use strict';

  var NS = 'http://www.w3.org/2000/svg';

  function el(name, attrs) {
    var node = document.createElementNS(NS, name);
    for (var key in attrs) {
      if (Object.prototype.hasOwnProperty.call(attrs, key)) {
        node.setAttribute(key, String(attrs[key]));
      }
    }
    return node;
  }

  function token(name, fallback) {
    var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return v || fallback;
  }

  /** Round to at most 1dp and strip a trailing .0, so axes read 12 not 12.0. */
  function fmt(value, prefix) {
    var n = Math.round(value * 10) / 10;
    var s = (n % 1 === 0) ? String(n) : n.toFixed(1);
    return (prefix || '') + s;
  }

  /**
   * Pick a "nice" axis maximum at or above the data peak, so gridlines land on
   * round numbers instead of arbitrary ones like 137.4.
   */
  function niceMax(max) {
    if (max <= 0) {
      return 1;
    }
    var mag = Math.pow(10, Math.floor(Math.log10(max)));
    var norm = max / mag;
    var step = norm <= 1 ? 1 : norm <= 2 ? 2 : norm <= 5 ? 5 : 10;
    return step * mag;
  }

  function svgRoot(width, height) {
    var svg = el('svg', {
      viewBox: '0 0 ' + width + ' ' + height,
      width: '100%',
      height: '100%',
      preserveAspectRatio: 'xMidYMid meet',
      role: 'img',
    });
    return svg;
  }

  /**
   * Shared plot frame: horizontal gridlines with value labels, and x labels
   * thinned so they never overlap at narrow widths.
   */
  function drawFrame(svg, box, maxValue, xLabels, prefix, colors) {
    var steps = 4;
    for (var i = 0; i <= steps; i++) {
      var v = (maxValue / steps) * i;
      var y = box.y + box.h - (box.h * (i / steps));
      svg.appendChild(el('line', {
        x1: box.x, y1: y, x2: box.x + box.w, y2: y,
        stroke: colors.border, 'stroke-width': 1,
      }));
      var label = el('text', {
        x: box.x - 8, y: y + 4, 'text-anchor': 'end',
        fill: colors.muted, 'font-size': 11,
      });
      label.textContent = fmt(v, prefix);
      svg.appendChild(label);
    }

    // Show at most 8 x labels; past that they collide and become unreadable.
    var every = Math.max(1, Math.ceil(xLabels.length / 8));
    xLabels.forEach(function (text, idx) {
      if (idx % every !== 0) {
        return;
      }
      var x = box.x + (box.w / xLabels.length) * (idx + 0.5);
      var label = el('text', {
        x: x, y: box.y + box.h + 18, 'text-anchor': 'middle',
        fill: colors.muted, 'font-size': 11,
      });
      label.textContent = text;
      svg.appendChild(label);
    });
  }

  function renderLine(host, series, prefix, colors) {
    var W = 640, H = 260;
    var box = { x: 52, y: 16, w: W - 68, h: H - 56 };
    var svg = svgRoot(W, H);
    var max = niceMax(Math.max.apply(null, series.map(function (d) { return d.value; })));

    drawFrame(svg, box, max, series.map(function (d) { return d.label; }), prefix, colors);

    var pts = series.map(function (d, i) {
      var x = box.x + (box.w / series.length) * (i + 0.5);
      var y = box.y + box.h - (d.value / max) * box.h;
      return [x, y];
    });

    // Area under the line, mixed from the accent so it stays quiet in both themes.
    if (pts.length > 1) {
      var area = 'M' + pts[0][0] + ',' + (box.y + box.h) +
                 pts.map(function (p) { return ' L' + p[0] + ',' + p[1]; }).join('') +
                 ' L' + pts[pts.length - 1][0] + ',' + (box.y + box.h) + ' Z';
      svg.appendChild(el('path', { d: area, fill: colors.accentQuiet, stroke: 'none' }));
    }

    svg.appendChild(el('path', {
      d: 'M' + pts.map(function (p) { return p[0] + ',' + p[1]; }).join(' L'),
      fill: 'none', stroke: colors.accent, 'stroke-width': 2,
      'stroke-linejoin': 'round', 'stroke-linecap': 'round',
    }));

    pts.forEach(function (p, i) {
      var dot = el('circle', { cx: p[0], cy: p[1], r: 3, fill: colors.accent });
      var t = el('title');
      t.textContent = series[i].label + ': ' + fmt(series[i].value, prefix);
      dot.appendChild(t);
      svg.appendChild(dot);
    });

    host.appendChild(svg);
  }

  function renderBar(host, series, prefix, colors) {
    var W = 640, H = 260;
    var box = { x: 52, y: 16, w: W - 68, h: H - 56 };
    var svg = svgRoot(W, H);
    var max = niceMax(Math.max.apply(null, series.map(function (d) { return d.value; })));

    drawFrame(svg, box, max, series.map(function (d) { return d.label; }), prefix, colors);

    var slot = box.w / series.length;
    var barW = Math.max(2, slot * 0.6);
    series.forEach(function (d, i) {
      var h = (d.value / max) * box.h;
      var rect = el('rect', {
        x: box.x + slot * i + (slot - barW) / 2,
        y: box.y + box.h - h,
        width: barW,
        height: Math.max(0, h),
        fill: colors.accent,
        rx: 2,
      });
      var t = el('title');
      t.textContent = d.label + ': ' + fmt(d.value, prefix);
      rect.appendChild(t);
      svg.appendChild(rect);
    });

    host.appendChild(svg);
  }

  function renderDoughnut(host, series, prefix, colors) {
    var W = 640, H = 260;
    var svg = svgRoot(W, H);
    var cx = 130, cy = H / 2, rOuter = 92, rInner = 58;
    var total = series.reduce(function (a, d) { return a + d.value; }, 0);

    if (total <= 0) {
      return;
    }

    // Segments vary by lightness along the accent hue rather than a fixed
    // greyscale ramp, so the wedges stay distinguishable in both themes.
    var angle = -Math.PI / 2;
    series.forEach(function (d, i) {
      var slice = (d.value / total) * Math.PI * 2;
      var end = angle + slice;
      var large = slice > Math.PI ? 1 : 0;
      var p = function (r, a) { return [cx + r * Math.cos(a), cy + r * Math.sin(a)]; };
      var a1 = p(rOuter, angle), a2 = p(rOuter, end);
      var b1 = p(rInner, end), b2 = p(rInner, angle);

      var path = el('path', {
        d: 'M' + a1 + ' A' + rOuter + ',' + rOuter + ' 0 ' + large + ' 1 ' + a2 +
           ' L' + b1 + ' A' + rInner + ',' + rInner + ' 0 ' + large + ' 0 ' + b2 + ' Z',
        fill: 'color-mix(in oklch, ' + colors.accent + ' ' +
              Math.max(18, 100 - i * 16) + '%, ' + colors.bg + ')',
      });
      var t = el('title');
      t.textContent = d.label + ': ' + fmt(d.value, prefix) +
                      ' (' + Math.round((d.value / total) * 100) + '%)';
      path.appendChild(t);
      svg.appendChild(path);
      angle = end;
    });

    // Legend to the right of the ring.
    series.forEach(function (d, i) {
      var y = 40 + i * 22;
      if (y > H - 16) {
        return;
      }
      svg.appendChild(el('rect', {
        x: 264, y: y - 9, width: 10, height: 10, rx: 2,
        fill: 'color-mix(in oklch, ' + colors.accent + ' ' +
              Math.max(18, 100 - i * 16) + '%, ' + colors.bg + ')',
      }));
      var label = el('text', { x: 282, y: y, fill: colors.text, 'font-size': 12 });
      label.textContent = d.label.length > 34 ? d.label.slice(0, 33) + '…' : d.label;
      svg.appendChild(label);
    });

    host.appendChild(svg);
  }

  function render(host) {
    var series;
    try {
      series = JSON.parse(host.dataset.series || '[]');
    } catch (e) {
      return;
    }
    if (!Array.isArray(series) || series.length === 0) {
      return;
    }

    var colors = {
      text: token('--text', '#111'),
      muted: token('--text-muted', '#777'),
      border: token('--border', '#eee'),
      accent: token('--accent', '#7a3f8f'),
      accentQuiet: token('--accent-quiet', '#f2e8f5'),
      bg: token('--bg', '#fff'),
    };

    host.textContent = '';
    var prefix = host.dataset.valuePrefix || '';

    if (host.dataset.chart === 'line') {
      renderLine(host, series, prefix, colors);
    } else if (host.dataset.chart === 'bar') {
      renderBar(host, series, prefix, colors);
    } else if (host.dataset.chart === 'doughnut') {
      renderDoughnut(host, series, prefix, colors);
    }
  }

  function init() {
    var hosts = document.querySelectorAll('[data-chart]');
    hosts.forEach(render);

    // Re-render on scheme change so the charts pick up the new token values;
    // they read the tokens once at draw time, not live.
    if (hosts.length && window.matchMedia) {
      window.matchMedia('(prefers-color-scheme: dark)')
        .addEventListener('change', function () { hosts.forEach(render); });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
