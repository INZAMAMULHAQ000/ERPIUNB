/**
 * Carbon Charts Bridge
 *
 * One global entry point for every chart in the ERP. Wraps @carbon/charts
 * (UMD, global `window.Charts`) so inline pages AND the widget engine share
 * the same theming, data-normalization and lifecycle contract.
 *
 *   window.renderCarbonChart(container, chartType, data, options)
 *     → { chart, destroy() }   or   null (unsupported / no data / lib missing)
 *
 * It accepts the exact data shape the app's Chart.js code already produces
 * ({labels, datasets:[{label, data, backgroundColor, borderColor}]}) and maps
 * it to Carbon Charts' {labels, datasets:[{label, data}]}, translating
 * backgroundColor/borderColor into Carbon's options.colors (keyed by label)
 * so the existing per-slice / per-series palettes survive.
 *
 * Themes: reads document.body[data-theme] at render time and maps it to a
 * Carbon theme token (g10 / g100). renderCarbonChart.reTheme() re-renders all
 * live charts after the theme toggle so SVG colors follow instantly.
 */

(function () {
  'use strict';

  var hasLib = function () { return typeof window.Charts !== 'undefined'; };

  // data-theme attribute value → Carbon Charts theme token.
  // (mirrors dashboard-core.js L286: light/candy/ibm-light → light, dark/kito/ibm-dark → dark)
  var THEME_TOKENS = {
    'light':     'g10',
    'dark':      'g100',
    'candy':     'g10',
    'kito':      'g100',
    'ibm-light': 'g10',
    'ibm-dark':  'g100'
  };

  var currentThemeToken = function () {
    var t = document.body.getAttribute('data-theme') || 'ibm-light';
    return THEME_TOKENS[t] || 'g10';
  };

  // chartType string → Charts constructor name.
  var TYPE_MAP = {
    'doughnut':    'DonutChart',
    'donut':       'DonutChart',
    'pie':         'PieChart',
    'line':        'LineChart',
    'area':        'AreaChart',
    'stackedarea': 'StackedAreaChart',
    'stacked-area':'StackedAreaChart',
    'grouped':     'GroupedBarChart',
    'stacked':     'StackedBarChart',
    'combo':       'ComboChart',
    'scatter':     'ScatterChart',
    'bubble':      'BubbleChart',
    'gauge':       'GaugeChart',
    'meter':       'MeterChart',
    'bullet':      'BulletChart',
    'radar':       'RadarChart',
    'histogram':   'HistogramChart',
    'boxplot':     'BoxplotChart',
    'heatmap':     'HeatmapChart',
    'tree':        'TreeChart',
    'treemap':     'TreemapChart',
    'circlepack':  'CirclePackChart',
    // v1.27.18 has no standalone PackChart — d3 "pack" layout is exposed as
    // CirclePackChart, so the two chartType aliases share that class.
    'pack':        'CirclePackChart',
    'alluvial':    'AlluvialChart',
    'wordcloud':   'WordCloudChart',
    'cloud':       'WordCloudChart',
    'lollipop':    'LollipopChart',
    'choropleth':  'ChoroplethChart',
    'inon':        'InOnChart'
  };

  // Cartesian chart classes render axes and crash in @carbon/charts@1.27.18
  // with "Cannot read properties of null (reading 'left')" if options.axes is
  // absent — the option merge yields null instead of the class defaults. The
  // bridge always injects minimal axes for these so every page degrades to a
  // rendered chart, never a blank container.
  var CARTESIAN = {
    'line': true, 'area': true, 'stacked-area': true, 'bar': true, 'grouped': true,
    'stacked': true, 'combo': true, 'scatter': true, 'bubble': true, 'histogram': true,
    'boxplot': true, 'heatmap': true, 'alluvial': true, 'lollipop': true,
    'horizontal': true, 'bullet': true
  };

  // Chart types whose data shape is NOT the {labels, datasets} envelope —
  // hierarchical ({tree, treemap, circlepack, pack}), graph ({alluvial}), raw
  // values ({histogram}), geo ({choropleth}), or experimental ({inon}). Their
  // data is passed through verbatim; only theme + lifecycle + axes apply.
  var PASSTHROUGH = {
    'tree': true, 'treemap': true, 'circlepack': true, 'pack': true,
    'alluvial': true, 'choropleth': true, 'inon': true, 'histogram': true
  };

  /** Normalize Chart.js-ish data → Carbon Charts {labels, datasets}. */
  function normalizeData(data) {
    if (!data || typeof data !== 'object') return null;
    var labels = Array.isArray(data.labels) ? data.labels : [];
    var raw = Array.isArray(data.datasets) ? data.datasets : [];
    if (labels.length === 0 || raw.length === 0) return null;

    var datasets = raw.map(function (ds) {
      var out = { label: ds.label || undefined };
      out.data = Array.isArray(ds.data) ? ds.data : [];
      return out;
    });

    // A chart whose datasets carry no values at all is pointless — skip.
    // All-zero values are legitimate (a 0% attendance day, $0 spent), so only
    // empty arrays no-op. The app's ['No data']/[1] fallbacks are non-empty
    // and still render.
    var hasValues = datasets.some(function (ds) { return ds.data.length > 0; });
    if (!hasValues) return null;

    return { labels: labels, datasets: datasets };
  }

  /**
   * Build per-series / per-slice color maps from the Chart.js-ish
   * backgroundColor/borderColor fields, keyed by data-group name.
   *
   * Carbon keys a series by its data group name — a dataset label on
   * cartesian charts, a slice label on pie/donut — and colours it via the
   * getFillColor/getStrokeColor options (options.colors only drives heatmap
   * gradients in v1.27.18). Array backgroundColor (per-slice doughnut/pie)
   * maps {labels[i] → bg[i]}; a single value colors the whole labelled
   * series. borderColor wins for strokes so lines keep their vivid accent
   * while the area fill stays the faint tint.
   */
  function buildColorMaps(data) {
    var fill = {}, stroke = {};
    data.datasets.forEach(function (ds) {
      var bg = ds.backgroundColor, bc = ds.borderColor;
      if (Array.isArray(bg)) {
        (data.labels || []).forEach(function (lb, li) {
          if (bg[li]) { fill[lb] = bg[li]; if (stroke[lb] === undefined) stroke[lb] = bg[li]; }
        });
      } else if (bg) {
        var key = ds.label != null && ds.label !== '' ? ds.label : ds;
        fill[key] = bg; if (stroke[key] === undefined) stroke[key] = bg;
      }
      if (Array.isArray(bc)) {
        (data.labels || []).forEach(function (lb, li) { if (bc[li]) stroke[lb] = bc[li]; });
      } else if (bc) {
        var k = ds.label != null && ds.label !== '' ? ds.label : ds;
        stroke[k] = bc; if (fill[k] === undefined) fill[k] = bc;
      }
    });
    return {
      fill: Object.keys(fill).length ? fill : null,
      stroke: Object.keys(stroke).length ? stroke : null
    };
  }

  /** Pick the Charts class for a chartType; 'bar' groups when 2+ datasets. */
  function resolveClass(chartType, data) {
    if (chartType === 'bar') {
      return data.datasets.length > 1 ? 'GroupedBarChart' : 'SimpleBarChart';
    }
    if (chartType === 'horizontal') {
      return 'SimpleBarChart';
    }
    return TYPE_MAP[chartType] || 'LineChart';
  }

  // Carbon chartType → Chart.js type for the degraded fallback path.
  var CHARTJS_TYPE = {
    'doughnut': 'doughnut', 'donut': 'doughnut', 'pie': 'pie',
    'line': 'line', 'area': 'line',
    'grouped': 'bar', 'stacked': 'bar', 'bar': 'bar', 'combo': 'bar',
    'scatter': 'scatter', 'bubble': 'bubble', 'radar': 'radar',
    'horizontal': 'bar'
  };

  /**
   * Chart.js fallback: when the Carbon bundle is unavailable (CDN blocked)
   * the bridge still renders the same {labels, datasets} envelope through
   * Chart.js (already loaded globally) so every converted page and the widget
   * engine keep their charts — never a blank card. Data passes through
   * verbatim: it already is Chart.js's shape, including backgroundColor/
   * borderColor arrays.
   */
  function renderChartJsFallback(container, chartType, data) {
    if (typeof window.Chart === 'undefined') return null;

    // Mirror the Carbon empty-data guard: honest "No data available" instead
    // of a dead card. All-zero values are legitimate and still render.
    var labels = data && Array.isArray(data.labels) ? data.labels : [];
    var datasets = data && Array.isArray(data.datasets) ? data.datasets : [];
    if (labels.length === 0 || datasets.length === 0) {
      if (container.children.length === 0) {
        container.setAttribute('data-carbon-chart', '');
        container.innerHTML = EMPTY_HTML;
      }
      return null;
    }

    var type = String(chartType || 'bar').toLowerCase();
    var cjsType = CHARTJS_TYPE[type] || 'bar';

    var canvas = document.createElement('canvas');
    container.appendChild(canvas);

    var opts = {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: datasets.length > 1 } }
    };
    if (type === 'horizontal') opts.indexAxis = 'y';
    if (type === 'stacked') {
      opts.scales = { x: { stacked: true }, y: { stacked: true } };
    }

    var chart;
    try {
      chart = new window.Chart(canvas.getContext('2d'), {
        type: cjsType,
        data: data,
        options: opts
      });
    } catch (e) {
      if (window.console) console.error('Chart.js fallback render error:', e);
      if (canvas.parentNode) canvas.parentNode.removeChild(canvas);
      return null;
    }
    return chart;
  }

  // Registry of live charts so reTheme() can re-render them all.
  var live = [];

  // Shown in a chart container when the data is empty, so an empty card reads
  // as "no data" rather than a broken-looking blank area.
  var EMPTY_HTML =
    '<div style="display:flex;align-items:center;justify-content:center;height:100%;min-height:240px;color:var(--cds-text-secondary,#8d8d8d);font-size:13px">' +
    'No data available' +
    '</div>';

  /**
   * Give a chart holder a DEFINITE height to break Carbon's ResizeObserver
   * feedback loop (the "chart grows infinitely to the bottom" bug).
   *
   * Carbon charts run with resizable:true (default) and, on each observed
   * resize, size the drawing area (`.full-frame`) to the holder's measured
   * height. When the holder is height:auto AND it also contains Carbon's fixed
   * header / spacer / toolbar children above the drawing area, the holder's
   * content is ALWAYS taller than the drawing area by that fixed amount — so
   * every cycle measures a holder that is header+spacer taller, sets the
   * drawing area to it, and the holder grows by that same fixed amount again.
   * Unbounded vertical expansion, ~55px per cycle here.
   *
   * A definite height makes the measured holder height constant, so the
   * observer converges on the first frame. The value is derived from the
   * holder's current rendered height (its CSS height/min-height — 240px by
   * default, 265px on the catalog's .cc-box) so pages keep their intended
   * size; callers can override via options.height (Carbon also applies it to
   * the holder itself).
   *
   * Carbon's own construction resets the holder's inline height once, so this
   * is applied AFTER `new ChartClass()` (see renderCarbonChart). That still
   * isn't enough on its own for re-renders into the SAME container (reTheme()
   * clearing innerHTML and rebuilding): Carbon's fresh layout ends up a bit
   * taller than the previous pass, and re-measuring after every rebuild would
   * compound that growth by the same amount each time. So the height is
   * determined ONCE per container (cached in pinnedHeights) and reused for
   * every later re-render — never re-measured — which is also what "pages
   * keep their intended size" actually requires.
   */
  var pinnedHeights = new WeakMap();
  function applyDefiniteHeight(container, options) {
    var userOptions = options && typeof options === 'object' ? options : {};
    var h;
    if (typeof userOptions.height === 'number' && userOptions.height > 0) {
      h = userOptions.height;
    } else if (pinnedHeights.has(container)) {
      h = pinnedHeights.get(container);
    } else {
      var cur = container.getBoundingClientRect().height;
      h = cur > 0 ? cur : 240;
    }
    h = Math.round(h);
    pinnedHeights.set(container, h);
    container.style.height = h + 'px';
  }

  function renderCarbonChart(container, chartType, data, options) {
    if (!container) return null;

    var userOptions = options && typeof options === 'object' ? options : {};

    // Replace any chart already bound to this container (Carbon or fallback).
    for (var i = live.length - 1; i >= 0; i--) {
      if (live[i].container === container) {
        try { live[i].chart.destroy(); } catch (e) {}
        live.splice(i, 1);
      }
    }

    // Pin the holder to a definite pixel height BEFORE any chart lib measures
    // it. Without this, a height:auto holder combined with a resize-observing
    // chart (Carbon's resizable:true, or Chart.js responsive canvas) causes an
    // unbounded growth loop — see applyDefiniteHeight() for the mechanism.
    applyDefiniteHeight(container, userOptions);

    // Carbon bundle unavailable (CDN blocked) → degrade to Chart.js so the
    // card is never blank. Same {chart, destroy()} surface and lifecycle
    // registry, so reTheme()/teardown treat it identically.
    if (!hasLib()) {
      var fb = renderChartJsFallback(container, chartType, data);
      if (!fb) return null;
      var fbInstance = {
        chart: fb,
        destroy: function () {
          for (var j = live.length - 1; j >= 0; j--) {
            if (live[j].instance === fbInstance) live.splice(j, 1);
          }
          try { fb.destroy(); } catch (e) {}
        }
      };
      live.push({
        container: container, chart: fb, instance: fbInstance,
        chartType: String(chartType || 'bar').toLowerCase(), data: data,
        userOptions: userOptions
      });
      return fbInstance;
    }

    var type = String(chartType || 'bar').toLowerCase();
    var isPassthrough = PASSTHROUGH[type] || false;

    var chartData;
    if (isPassthrough) {
      chartData = (data && typeof data === 'object') ? data : null;
    } else {
      chartData = normalizeData(data);
    }
    if (!chartData) {
      // No values to plot — leave an honest empty state (only when the
      // container is otherwise blank) instead of a dead card.
      if (container.children.length === 0) {
        container.setAttribute('data-carbon-chart', '');
        container.innerHTML = EMPTY_HTML;
      }
      return null;
    }

    var cls = resolveClass(type, chartData);
    var ChartClass = window.Charts[cls];
    if (!ChartClass) return null;

    var opts = Object.assign({}, userOptions);
    opts.theme = currentThemeToken();

    // Cartesian charts need axes present (see CARTESIAN note). Merge so a
    // caller-supplied axes config survives; otherwise Carbon infers scales.
    if (CARTESIAN[type]) {
      opts.axes = Object.assign({ left: {}, bottom: {} }, opts.axes);
    }

    // Horizontal bar → SimpleBarChart orientation.
    if (type === 'horizontal' || userOptions.horizontal) {
      opts.barOptions = Object.assign({}, opts.barOptions, { orientation: 'horizontal' });
    }

    // Preserve existing color palettes via Carbon's getFillColor/getStrokeColor
    // callbacks (keyed by data-group name = dataset label / slice label).
    // Uses the ORIGINAL data — normalizeData drops the color fields.
    // Skipped for passthrough types whose data has no label-keyed color info.
    var colorMaps = isPassthrough ? null : buildColorMaps(data);
    if (colorMaps && colorMaps.fill) {
      var fillMap = colorMaps.fill;
      opts.getFillColor = function (group) { return fillMap[group] || undefined; };
    }
    if (colorMaps && colorMaps.stroke) {
      var strokeMap = colorMaps.stroke;
      opts.getStrokeColor = function (group) { return strokeMap[group] || undefined; };
    }

    // Carbon needs a real height on the container (SVG, not canvas).
    container.setAttribute('data-carbon-chart', '');

    // Reset any height pinned by a PREVIOUS render (e.g. reTheme() rebuilding
    // this same container) back to the CSS baseline (min-height, 240px by
    // default) before construction. Otherwise Carbon's layout reads that
    // stale, already-grown inline height while laying out the new chart and
    // compounds it — the holder grows another header+spacer's worth on every
    // re-render instead of converging on a stable size.
    container.style.height = '';

    var chart;
    try {
      chart = new ChartClass(container, { data: chartData, options: opts });
    } catch (e) {
      if (window.console) console.error('Carbon chart render error:', e);
      return null;
    }

    // Carbon's own init resets the holder's inline height (as part of its
    // construction) AFTER we set it above, which is what defeats a pre-
    // construction call to applyDefiniteHeight() and lets the growth loop
    // restart. Pin it again now, once construction has settled — nothing in
    // this module touches the holder's height afterwards, so this value
    // sticks and the ResizeObserver converges on it.
    applyDefiniteHeight(container, userOptions);

    var instance = {
      chart: chart,
      destroy: function () {
        for (var j = live.length - 1; j >= 0; j--) {
          if (live[j].instance === instance) live.splice(j, 1);
        }
        try { chart.destroy(); } catch (e) {}
      }
    };

    live.push({
      container: container,
      chart: chart,
      instance: instance,
      chartType: type,
      data: chartData,
      userOptions: userOptions
    });

    return instance;
  }

  /**
   * Re-render every live chart with the current theme token.
   *
   * NOTE: never call chart.destroy() here. In @carbon/charts@1.27.18,
   * Chart.destroy() runs domUtils.getHolder().remove() — and the holder IS the
   * container element the chart was constructed with. Destroying a live chart
   * therefore rips its container <div> out of the document, so the re-render
   * would target a detached node and silently vanish. Instead we clear the
   * container's markup and let renderCarbonChart rebuild the chart in place —
   * the container stays connected, the new theme token applies, nothing stacks.
   */
  renderCarbonChart.reTheme = function () {
    if (!hasLib()) return;
    var entries = live.slice();
    entries.forEach(function (e) {
      if (!e.container || !e.container.isConnected) return;
      e.container.innerHTML = '';
      for (var i = live.length - 1; i >= 0; i--) {
        if (live[i].instance === e.instance) live.splice(i, 1);
      }
      renderCarbonChart(e.container, e.chartType, e.data, e.userOptions);
    });
  };

  /** Current Carbon theme token (useful for catalog / debug). */
  renderCarbonChart.theme = currentThemeToken;

  /** Number of live charts (debug). */
  renderCarbonChart.count = function () { return live.length; };

  window.renderCarbonChart = renderCarbonChart;
})();
