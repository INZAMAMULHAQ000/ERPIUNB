/**
 * Dashboard Core Engine
 *
 * Manages widget layout, data fetching, and rendering.
 * Usage: new Dashboard(containerId, options)
 */

class Dashboard {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        if (!this.container) throw new Error('Container not found: ' + containerId);

        this.options = Object.assign({
            dashboardKey: 'main',
            columns: 4,
            editMode: false,
            apiBase: 'api/dashboard',
            widgetApiBase: 'api/widgets',
            csrfToken: '',
            renderOnInit: true,
            onSave: null,
            onError: null,
            onLayoutLoad: null,
            chartColors: ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#f97316', '#84cc16']
        }, options);

        this.widgets = {};
        this.layout = null;
        this.isCustomized = false;
        this.charts = {};
        this.editMode = this.options.editMode;

        // Bind methods
        this.render = this.render.bind(this);
        this.loadLayout = this.loadLayout.bind(this);
        this.toggleEditMode = this.toggleEditMode.bind(this);
        this.removeWidget = this.removeWidget.bind(this);
        this.addWidget = this.addWidget.bind(this);
        this.resetLayout = this.resetLayout.bind(this);

        // Initialize
        this.init();
    }

    async init() {
        try {
            await this.loadLayout();
            // Fire onLayoutLoad (which swaps the old static content for this
            // grid) as soon as we know the layout, not after render() also
            // finishes fetching every widget's data. render() awaits a
            // network round-trip per widget, so waiting for it here left the
            // legacy dashboard fully visible that whole time and then hid it
            // all at once — a jarring "content flashes then disappears" swap.
            if (this.options.onLayoutLoad) {
                this.options.onLayoutLoad(this.isCustomized);
            }
            if (this.options.renderOnInit) {
                await this.render();
            }
            this.setupEditToggle();
        } catch (err) {
            console.error('Dashboard init error:', err);
            this.showError('Failed to initialize dashboard');
        }
    }

    // ── Layout Loading ──────────────────────────────────────────
    async loadLayout() {
        const url = `${this.options.apiBase}/layout.php?dashboard=${this.options.dashboardKey}`;
        const resp = await fetch(url);
        const result = await resp.json();

        if (!result.success) {
            throw new Error(result.error || 'Failed to load layout');
        }

        this.layout = result.layout;
        this.isCustomized = result.is_customized || false;
        return this.layout;
    }

    // ── Rendering ───────────────────────────────────────────────
    async render() {
        this.container.innerHTML = '';

        // Create toolbar
        this.createToolbar();

        // Create grid
        this.grid = document.createElement('div');
        this.grid.className = 'dashboard-grid';
        this.grid.style.display = 'grid';
        this.grid.style.gridTemplateColumns = `repeat(${this.layout.columns || 4}, 1fr)`;
        this.grid.style.gap = '1rem';
        this.grid.style.gridAutoRows = 'minmax(100px, auto)';
        this.container.appendChild(this.grid);

        // Get enabled widgets sorted by position
        const enabledWidgets = Object.entries(this.layout.widgets || {})
            .filter(([key, w]) => w.enabled !== false)
            .sort((a, b) => a[1].order - b[1].order);

        if (enabledWidgets.length === 0) {
            this.showEmptyState();
            return;
        }

        // Create placeholder for each widget
        for (const [widgetKey, widgetConfig] of enabledWidgets) {
            this.createWidgetPlaceholder(widgetKey, widgetConfig);
        }

        // Fetch and render widget data
        const fetchPromises = enabledWidgets.map(([widgetKey]) =>
            this.fetchWidgetData(widgetKey)
        );

        const results = await Promise.allSettled(fetchPromises);
        results.forEach((result, index) => {
            const [widgetKey, widgetConfig] = enabledWidgets[index];
            if (result.status === 'fulfilled') {
                this.renderWidgetData(widgetKey, widgetConfig, result.value);
            } else {
                this.renderWidgetError(widgetKey, widgetConfig, result.reason);
            }
        });

        // Setup drag-drop in edit mode
        if (this.editMode) {
            this.setupDragDrop();
        }
    }

    createToolbar() {
        const toolbar = document.createElement('div');
        toolbar.className = 'dashboard-toolbar mb-4 flex justify-between items-center';

        const title = document.createElement('div');
        title.innerHTML = `
            <div class="flex items-center gap-3">
                <h2 class="text-lg font-semibold text-slate-800">Dashboard</h2>
                ${this.isCustomized ? '<span class="text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full font-medium">Customized</span>' : ''}
            </div>
        `;

        const controls = document.createElement('div');
        controls.className = 'flex items-center gap-2';
        controls.innerHTML = `
            <button class="dash-btn dash-btn-ghost toggle-edit-btn" title="Toggle Edit Mode">
                <i class="fas fa-${this.editMode ? 'check' : 'pencil-alt'}"></i>
                <span>${this.editMode ? 'Done' : 'Customize'}</span>
            </button>
        `;

        // Reset button (only in edit mode)
        if (this.isCustomized) {
            const resetBtn = document.createElement('button');
            resetBtn.className = 'dash-btn dash-btn-ghost text-red-600 hover:text-red-700 reset-layout-btn';
            resetBtn.innerHTML = '<i class="fas fa-undo"></i><span>Reset</span>';
            resetBtn.title = 'Restore default layout';
            resetBtn.addEventListener('click', (e) => {
                if (confirm('Reset your dashboard to defaults? This cannot be undone.')) {
                    this.resetLayout();
                }
            });
            controls.appendChild(resetBtn);
        }

        toolbar.appendChild(title);
        toolbar.appendChild(controls);
        this.container.appendChild(toolbar);
    }

    createWidgetPlaceholder(widgetKey, config) {
        const el = document.createElement('div');
        el.className = 'dashboard-widget bg-white rounded-xl shadow-sm border border-slate-100 p-4';
        el.id = 'widget-' + (config.instance_id || widgetKey);
        el.dataset.widgetKey = widgetKey;
        el.dataset.instanceId = config.instance_id || '';
        el.style.gridColumn = `span ${config.width || 1}`;
        el.style.gridRow = `span ${config.height || 1}`;

        // Widget header
        const header = document.createElement('div');
        header.className = 'widget-header flex justify-between items-center mb-3';
        header.innerHTML = `
            <h4 class="text-sm font-semibold text-slate-700 widget-title">${widgetKey.replace(/_/g, ' ')}</h4>
            <div class="widget-controls flex items-center gap-1 ${this.editMode ? '' : 'hidden'}">
                <button class="widget-remove-btn text-red-400 hover:text-red-600 text-xs p-1" title="Remove widget">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

        // Loading indicator
        const body = document.createElement('div');
        body.className = 'widget-body';
        body.innerHTML = '<div class="flex items-center justify-center py-8"><i class="fas fa-spinner fa-spin text-slate-300 text-xl"></i></div>';

        el.appendChild(header);
        el.appendChild(body);
        this.grid.appendChild(el);

        // Store reference
        this.widgets[widgetKey] = { el, config, header, body };

        // Bind remove button
        const removeBtn = header.querySelector('.widget-remove-btn');
        if (removeBtn) {
            removeBtn.addEventListener('click', () => this.removeWidget(widgetKey));
        }
    }

    async fetchWidgetData(widgetKey) {
        const url = `${this.options.widgetApiBase}/data.php?widget=${widgetKey}&_=${Date.now()}`;
        const resp = await fetch(url);
        return await resp.json();
    }

    renderWidgetData(widgetKey, config, result) {
        const widget = this.widgets[widgetKey];
        if (!widget) return;

        if (!result.success) {
            this.renderWidgetError(widgetKey, config, result.error);
            return;
        }

        const data = result.data;

        // Update title from definition if available
        const titleEl = widget.el.querySelector('.widget-title');
        if (titleEl && result.definition_name) {
            titleEl.textContent = result.definition_name;
        }

        // Handle different render types
        switch (result.render_type || 'server_html') {
            case 'server_html':
                this.renderServerHtml(widget, result);
                break;
            case 'chartjs':
                this.renderChart(widget, result);
                break;
            case 'hybrid':
                this.renderServerHtml(widget, result);
                break;
            default:
                widget.body.innerHTML = '<div class="text-sm text-slate-400 text-center py-4">Unknown render type</div>';
        }
    }

    renderServerHtml(widget, result) {
        const data = result.data;
        if (!data || data.empty) {
            widget.body.innerHTML = `<div class="text-sm text-slate-400 text-center py-6">${data.message || 'No data available'}</div>`;
            return;
        }

        // Let the server return HTML, or render client-side
        widget.body.innerHTML = this.buildWidgetHtml(result);
    }

    renderChart(widget, result) {
        const widgetEl = widget.el;
        const widgetKey = widgetEl.dataset.widgetKey;

        // Wait for DOM update, then init chart
        requestAnimationFrame(() => {
            const chartData = result.data;
            const chartType = result.chart_type || 'bar';
            const chartOptions = result.chart_options || {};

            // Destroy existing chart if any
            if (this.charts[widgetKey]) {
                this.charts[widgetKey].destroy();
            }

            // Preferred renderer: Carbon Charts (SVG) via the shared bridge.
            // Renders into a plain div sized by the [data-carbon-chart] CSS rule;
            // the bridge owns theme + data normalization + lifecycle.
            if (typeof window.renderCarbonChart === 'function' && typeof window.Charts !== 'undefined') {
                const containerId = 'chart-' + (widget.config.instance_id || widgetKey) + '-' + Date.now();
                widget.body.innerHTML = `<div id="${containerId}"></div>`;
                widget.body.style.minHeight = '240px';

                const instance = window.renderCarbonChart(
                    document.getElementById(containerId),
                    chartType,
                    chartData,
                    chartOptions
                );
                if (instance) {
                    this.charts[widgetKey] = instance;
                    return;
                }
                // renderCarbonChart returned null (empty data / unsupported type) —
                // fall through to Chart.js rather than leaving a blank card.
            }

            // Chart.js fallback (unchanged): blocked Carbon CDN or no-op above.
            const canvasId = 'chart-' + (widget.config.instance_id || widgetKey) + '-' + Date.now();
            widget.body.innerHTML = `<canvas id="${canvasId}"></canvas>`;
            const canvas = document.getElementById(canvasId);
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            if (!ctx || typeof Chart === 'undefined') {
                widget.body.innerHTML = '<div class="text-sm text-slate-400 text-center py-4">Chart.js not loaded</div>';
                return;
            }

            const isDark = ['dark', 'kito', 'ibm-dark'].indexOf(document.body.getAttribute('data-theme')) !== -1;
            const txtColor = isDark ? '#94a3b8' : '#64748b';
            const gridColor = isDark ? '#1e293b' : '#f1f5f9';

            const defaultOptions = {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        labels: { color: txtColor, font: { size: 11 } }
                    }
                },
                scales: chartType !== 'doughnut' && chartType !== 'pie' ? {
                    x: { ticks: { color: txtColor, font: { size: 10 } }, grid: { color: gridColor } },
                    y: { ticks: { color: txtColor, font: { size: 10 } }, grid: { color: gridColor }, beginAtZero: true }
                } : undefined
            };

            const options = Object.assign(defaultOptions, chartOptions);

            try {
                this.charts[widgetKey] = new Chart(ctx, {
                    type: chartType,
                    data: chartData,
                    options
                });
            } catch (e) {
                console.error('Chart init error:', e);
                widget.body.innerHTML = '<div class="text-sm text-slate-400 text-center py-4">Chart render error</div>';
            }
        });
    }

    buildWidgetHtml(result) {
        const data = result.data;
        const widgetKey = result.widget_key;

        // Generic HTML rendering based on data structure
        // KPI card
        if (data.value !== undefined && data.label !== undefined) {
            return this.buildKpiHtml(data);
        }

        // Bar chart items (module activity style)
        if (data.items && data.max && data.labels) {
            return this.buildBarItemsHtml(data);
        }

        // Timeline items
        if (data.items && data.items.length > 0 && data.items[0].action !== undefined) {
            return this.buildTimelineHtml(data.items);
        }

        // Generic list items
        if (data.items && Array.isArray(data.items)) {
            if (data.items.length === 0) {
                return '<div class="text-sm text-slate-400 text-center py-4">No data available</div>';
            }

            // Check if it's a table-type data
            if (data.items[0].ticket_number) {
                return this.buildTicketsTableHtml(data);
            }

            if (data.items[0].revenue !== undefined) {
                return this.buildClientBarHtml(data);
            }

            if (data.items[0].resolved_count !== undefined) {
                return this.buildAgentListHtml(data.items);
            }

            if (data.items[0].category_name && data.items[0].ticket_count !== undefined) {
                return this.buildCategoryListHtml(data.items);
            }

            if (data.items[0].label !== undefined && data.items[0].count !== undefined && data.items[0].color !== undefined) {
                return this.buildPriorityBarHtml(data);
            }

            if (data.items[0].hours_elapsed !== undefined) {
                return this.buildSlaAlertHtml(data.items);
            }

            if (data.items[0].client_name !== undefined) {
                return this.buildPendingResponseHtml(data.items);
            }

            if (data.items[0].url !== undefined) {
                return this.buildQuickActionsHtml(data.items);
            }

            // Generic list
            return this.buildGenericListHtml(data.items);
        }

        // Pending items (4 stat cards)
        if (data.pending_leaves !== undefined) {
            return this.buildPendingItemsHtml(data);
        }

        // Employee balances
        if (data.employees && Array.isArray(data.employees)) {
            return this.buildEmployeeBalancesHtml(data);
        }

        // Analytics metrics
        if (data.total_employees !== undefined) {
            return this.buildAnalyticsHtml(data);
        }

        // Empty fallback
        return '<div class="text-sm text-slate-400 text-center py-4">No data available</div>';
    }

    // ── HTML Builders ───────────────────────────────────────────

    buildKpiHtml(data) {
        const val = data.format === 'money' && typeof formatMoney === 'function'
            ? formatMoney(data.value)
            : data.value;
        const trendHtml = data.trend !== null ? `
            <p class="text-${data.color || 'green'}-100 text-xs mt-1">
                ${data.trend_label || ''} ${data.trend}
            </p>
        ` : '';

        return `
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-${data.color || 'gray'}-100 text-xs uppercase font-medium">${data.label || ''}</p>
                    <p class="text-2xl font-bold mt-1">${val}</p>
                    ${trendHtml}
                </div>
                <i class="${data.icon || 'fas fa-chart-bar'} text-${data.color || 'gray'}-200 text-2xl"></i>
            </div>
        `;
    }

    buildBarItemsHtml(data) {
        let html = '<div class="space-y-3">';
        data.items.forEach(item => {
            const pct = data.max > 0 ? Math.round((item.cnt / data.max) * 100) : 0;
            html += `
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="text-slate-600 font-medium">${this.esc(item.module || item.name)}</span>
                        <span class="text-slate-400">${item.cnt}</span>
                    </div>
                    <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all" style="width: ${pct}%; background: var(--primary)"></div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        return html;
    }

    buildTimelineHtml(items) {
        let html = '<div class="space-y-0">';
        items.forEach(item => {
            const dotColor = item.action === 'create' ? 'bg-emerald-400'
                : item.action === 'update' ? 'bg-amber-400'
                : item.action === 'delete' ? 'bg-rose-400' : 'bg-slate-300';
            html += `
                <div class="flex items-start gap-3 py-2.5 border-b border-slate-50 last:border-0">
                    <div class="w-1.5 h-1.5 rounded-full mt-2 flex-shrink-0 ${dotColor}"></div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-slate-600 truncate">
                            <span class="font-medium text-slate-800">${this.esc(item.user_name || 'System')}</span>
                            <span class="text-slate-400">${this.esc(item.action)}</span>
                            <span class="text-slate-500">${this.esc(item.module)}</span>
                        </p>
                    </div>
                    <span class="text-[10px] text-slate-400 flex-shrink-0">${item.created_at ? this.formatDate(item.created_at) : ''}</span>
                </div>
            `;
        });
        html += '</div>';
        return html;
    }

    buildPriorityBarHtml(data) {
        let html = '<div class="space-y-3">';
        data.items.forEach(item => {
            const pct = data.max > 0 ? (item.count / data.max) * 100 : 0;
            html += `
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span>${item.label}</span>
                        <span class="font-bold ${item.text}">${item.count}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="${item.color} h-2 rounded-full" style="width: ${pct}%"></div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        return html;
    }

    buildSlaAlertHtml(items) {
        let html = '<div class="space-y-3">';
        items.forEach(item => {
            html += `
                <div class="border-l-4 border-orange-400 bg-orange-50 p-3 rounded">
                    <div class="flex justify-between items-start">
                        <div>
                            <span class="font-mono text-xs text-blue-600">${this.esc(item.ticket_number)}</span>
                            <p class="text-sm font-medium truncate">${this.esc(item.subject)}</p>
                        </div>
                        <span class="text-xs text-orange-600 font-bold">${item.hours_elapsed}h / ${item.resolution_time_hours}h</span>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        return html;
    }

    buildPendingResponseHtml(items) {
        let html = '<div class="space-y-3">';
        items.forEach(item => {
            html += `
                <div class="border-l-4 border-purple-400 bg-purple-50 p-3 rounded">
                    <span class="font-mono text-xs text-blue-600">${this.esc(item.ticket_number)}</span>
                    <p class="text-sm font-medium truncate">${this.esc(item.subject)}</p>
                    <p class="text-xs text-gray-500">${this.esc(item.client_name || 'No client')} - ${this.formatDate(item.created_at)}</p>
                </div>
            `;
        });
        html += '</div>';
        return html;
    }

    buildTicketsTableHtml(data) {
        const priorityColors = data.priority_colors || {};
        const statusColors = data.status_colors || {};
        let html = '<div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-gray-50"><tr>';
        html += '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ticket</th>';
        html += '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Subject</th>';
        html += '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Priority</th>';
        html += '<th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>';
        html += '</tr></thead><tbody class="divide-y">';
        data.items.forEach(item => {
            html += `
                <tr class="hover:bg-gray-50">
                    <td class="px-3 py-2 font-mono text-xs text-blue-600">${this.esc(item.ticket_number)}</td>
                    <td class="px-3 py-2 truncate max-w-xs">${this.esc(item.subject)}</td>
                    <td class="px-3 py-2"><span class="px-1.5 py-0.5 text-xs rounded ${priorityColors[item.priority] || ''}">${this.esc(item.priority)}</span></td>
                    <td class="px-3 py-2"><span class="px-1.5 py-0.5 text-xs rounded ${statusColors[item.status] || ''}">${this.esc(item.status.replace('_', ' '))}</span></td>
                </tr>
            `;
        });
        html += '</tbody></table></div>';
        return html;
    }

    buildClientBarHtml(data) {
        const maxRev = data.max_revenue || 0;
        let html = '<div class="space-y-3">';
        data.items.forEach(item => {
            const pct = maxRev > 0 ? Math.round((item.revenue / maxRev) * 100) : 0;
            html += `
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-700 truncate">${this.esc(item.name)}</span>
                        <span class="font-medium text-gray-800 ml-2">$${this.formatNum(item.revenue)}</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2">
                        <div class="bg-blue-500 h-2 rounded-full" style="width: ${pct}%"></div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        return html;
    }

    buildAgentListHtml(items) {
        let html = '<div class="space-y-3">';
        items.forEach((item, idx) => {
            const highlight = idx === 0 ? 'bg-yellow-50 border border-yellow-200 rounded' : 'border-b';
            html += `
                <div class="flex items-center gap-3 p-3 ${highlight}">
                    <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center text-white text-sm font-bold">
                        ${(item.first_name || 'U')[0].toUpperCase()}
                    </div>
                    <div class="flex-1">
                        <div class="font-medium">${this.esc(item.first_name + ' ' + item.last_name)}</div>
                        <div class="text-xs text-gray-500">${item.ticket_count} tickets assigned</div>
                    </div>
                    <div class="text-right">
                        <div class="text-green-600 font-bold">${item.resolved_count || 0}</div>
                        <div class="text-xs text-gray-500">resolved</div>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        return html;
    }

    buildCategoryListHtml(items) {
        let html = '<div class="space-y-3">';
        items.forEach(item => {
            html += `
                <div class="flex items-center justify-between p-2 border-b last:border-0">
                    <div>
                        <span class="font-medium">${this.esc(item.category_name)}</span>
                        <span class="text-xs text-gray-400 ml-2">${this.esc(item.category_code || '')}</span>
                    </div>
                    <div class="text-sm">
                        <span class="text-gray-600">${item.ticket_count} total</span>
                        ${item.open_count > 0 ? `<span class="text-blue-600 ml-2">${item.open_count} open</span>` : ''}
                    </div>
                </div>
            `;
        });
        html += '</div>';
        return html;
    }

    buildQuickActionsHtml(items) {
        let html = '<div class="grid grid-cols-2 gap-3">';
        items.forEach(item => {
            html += `
                <a href="${this.esc(item.url)}" class="flex items-center gap-3 p-4 bg-${item.color}-50 rounded-lg hover:bg-${item.color}-100 transition">
                    <i class="${item.icon} text-${item.color}-600 text-xl"></i>
                    <div>
                        <div class="font-medium text-sm">${this.esc(item.label)}</div>
                        <div class="text-xs text-gray-500">${this.esc(item.desc || '')}</div>
                    </div>
                </a>
            `;
        });
        html += '</div>';
        return html;
    }

    buildPendingItemsHtml(data) {
        return `
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center"><i class="fas fa-calendar-alt text-amber-500 text-sm"></i></div>
                        <div><p class="text-xs text-slate-400 uppercase tracking-wide font-semibold">Pending Leaves</p><p class="text-lg font-bold text-slate-800">${data.pending_leaves}</p></div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-rose-50 flex items-center justify-center"><i class="fas fa-receipt text-rose-500 text-sm"></i></div>
                        <div><p class="text-xs text-slate-400 uppercase tracking-wide font-semibold">Pending Expenses</p><p class="text-lg font-bold text-slate-800">${data.pending_expenses}</p></div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center"><i class="fas fa-laptop text-indigo-500 text-sm"></i></div>
                        <div><p class="text-xs text-slate-400 uppercase tracking-wide font-semibold">Active Assets</p><p class="text-lg font-bold text-slate-800">${data.active_assignments}</p></div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center"><i class="fas fa-check-circle text-emerald-500 text-sm"></i></div>
                        <div><p class="text-xs text-slate-400 uppercase tracking-wide font-semibold">Today Attendance</p><p class="text-lg font-bold text-slate-800">${data.today_attendance}</p></div>
                    </div>
                </div>
            </div>
        `;
    }

    buildEmployeeBalancesHtml(data) {
        if (data.empty) return '<div class="text-sm text-slate-400 text-center py-4">No employees found</div>';
        let html = '<div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-gray-50"><tr>';
        html += '<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee</th>';
        html += '<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Dept</th>';
        html += '<th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Total</th>';
        html += '<th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Used</th>';
        html += '<th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">Remaining</th>';
        if (data.leave_types) {
            data.leave_types.forEach(lt => {
                html += `<th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase">${this.esc(lt.leave_code)}</th>`;
            });
        }
        html += '</tr></thead><tbody class="divide-y divide-gray-200">';
        data.employees.forEach(eb => {
            const info = eb.info;
            const pct = eb.total_allocated > 0 ? Math.round((eb.total_remaining / eb.total_allocated) * 100) : 0;
            const colorClass = pct > 50 ? 'text-green-600' : pct > 25 ? 'text-yellow-600' : 'text-red-600';
            html += `
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3"><div class="font-medium text-gray-900 text-sm">${this.esc(info.first_name + ' ' + info.last_name)}</div><div class="text-xs text-gray-500">${this.esc(info.employee_code)}</div></td>
                    <td class="px-4 py-3 text-xs text-gray-600">${this.esc(info.department_name || '-')}</td>
                    <td class="px-4 py-3 text-center font-medium">${eb.total_allocated}</td>
                    <td class="px-4 py-3 text-center font-medium">${eb.total_used}</td>
                    <td class="px-4 py-3 text-center font-medium ${colorClass}">${eb.total_remaining}</td>
            `;
            if (data.leave_types) {
                data.leave_types.forEach(lt => {
                    const match = eb.balances.find(b => b.leave_name === lt.leave_name);
                    const rem = match ? (match.allocated - match.used) : '-';
                    const remClass = (typeof rem === 'number' && rem <= 0) ? 'text-red-500 font-medium' : 'text-gray-600';
                    html += `<td class="px-3 py-3 text-center text-xs ${remClass}">${rem}</td>`;
                });
            }
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        return html;
    }

    buildAnalyticsHtml(data) {
        let html = '<div class="grid grid-cols-2 gap-4">';
        Object.entries(data).forEach(([key, val]) => {
            const label = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            html += `
                <div class="bg-white rounded-lg p-4 border">
                    <p class="text-xs text-gray-500 uppercase font-medium">${label}</p>
                    <p class="text-xl font-bold text-gray-800 mt-1">${val}</p>
                </div>
            `;
        });
        html += '</div>';
        return html;
    }

    buildGenericListHtml(items) {
        let html = '<div class="space-y-2">';
        items.forEach(item => {
            const firstVal = Object.values(item)[0] || '';
            html += `<div class="flex justify-between items-center p-2 border-b last:border-0 text-sm"><span>${this.esc(firstVal)}</span></div>`;
        });
        html += '</div>';
        return html;
    }

    // ── Edit Mode ───────────────────────────────────────────────
    toggleEditMode() {
        this.editMode = !this.editMode;
        const btn = this.container.querySelector('.toggle-edit-btn');
        if (btn) {
            btn.innerHTML = `<i class="fas fa-${this.editMode ? 'check' : 'pencil-alt'}"></i><span>${this.editMode ? 'Done' : 'Customize'}</span>`;
        }

        // Show/hide widget controls
        document.querySelectorAll('.widget-controls').forEach(el => {
            el.classList.toggle('hidden', !this.editMode);
        });

        // Show/hide catalog
        const catalog = document.getElementById('widget-catalog');
        if (catalog) {
            catalog.classList.toggle('hidden', !this.editMode);
        }

        // Add drag handles in edit mode
        if (this.editMode) {
            this.setupDragDrop();
            this.loadCatalog();
        }
    }

    setupEditToggle() {
        this.container.addEventListener('click', (e) => {
            const btn = e.target.closest('.toggle-edit-btn');
            if (btn) this.toggleEditMode();
        });
    }

    // ── Widget Management ───────────────────────────────────────
    removeWidget(widgetKey) {
        if (!this.layout.widgets[widgetKey]) return;

        // Move to hidden list instead of deleting
        if (!this.layout.hidden_widgets) this.layout.hidden_widgets = [];
        this.layout.hidden_widgets.push(widgetKey);
        this.layout.widgets[widgetKey].enabled = false;

        // Remove from DOM
        const widget = this.widgets[widgetKey];
        if (widget && widget.el) {
            widget.el.remove();
        }

        delete this.widgets[widgetKey];
        this.autoSave();
    }

    async addWidget(widgetKey, definition) {
        // Determine position (bottom-right of current layout)
        let maxX = 0, maxY = 0;
        Object.values(this.layout.widgets).forEach(w => {
            if (w.enabled) {
                maxX = Math.max(maxX, w.x + w.width);
                maxY = Math.max(maxY, w.y + w.height);
            }
        });

        // Place at next available position
        let x = maxX % (this.layout.columns || 4);
        let y = maxY + (x === 0 ? 0 : 1);
        if (x === 0 && y === 0) y = 0;

        this.layout.widgets[widgetKey] = {
            instance_id: 'w_' + Date.now(),
            enabled: true,
            x: x,
            y: y,
            width: definition.default_width || 1,
            height: definition.default_height || 1,
            settings: {},
            order: Object.keys(this.layout.widgets).length
        };

        // Remove from hidden list if it was there
        if (this.layout.hidden_widgets) {
            this.layout.hidden_widgets = this.layout.hidden_widgets.filter(k => k !== widgetKey);
        }

        // Re-render
        await this.render();
        this.autoSave();
    }

    async resetLayout() {
        const url = `${this.options.apiBase}/layout.php?dashboard=${this.options.dashboardKey}&action=reset`;

        const resp = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.options.csrfToken
            }
        });

        const result = await resp.json();
        if (result.success) {
            this.isCustomized = false;
            await this.loadLayout();
            await this.render();
        } else {
            this.showError(result.error || 'Reset failed');
        }
    }

    async autoSave() {
        try {
            const resp = await fetch(`${this.options.apiBase}/layout.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.options.csrfToken
                },
                body: JSON.stringify(this.layout)
            });
            const result = await resp.json();
            if (result.success) {
                this.isCustomized = true;
            }
            if (this.options.onSave) this.options.onSave(result);
        } catch (err) {
            console.error('Auto-save error:', err);
        }
    }

    // ── Drag & Drop ─────────────────────────────────────────────
    setupDragDrop() {
        // Make widget headers draggable
        document.querySelectorAll('.dashboard-widget .widget-header').forEach(header => {
            header.draggable = true;

            header.addEventListener('dragstart', (e) => {
                const widget = header.closest('.dashboard-widget');
                e.dataTransfer.setData('text/plain', widget.id);
                widget.classList.add('dragging');
            });

            header.addEventListener('dragend', (e) => {
                const widget = header.closest('.dashboard-widget');
                widget.classList.remove('dragging');
                document.querySelectorAll('.dashboard-widget').forEach(el => {
                    el.classList.remove('drag-over');
                });
            });
        });

        // Setup drop zones
        document.querySelectorAll('.dashboard-widget').forEach(el => {
            el.addEventListener('dragover', (e) => {
                e.preventDefault();
                el.classList.add('drag-over');
            });
            el.addEventListener('dragleave', () => {
                el.classList.remove('drag-over');
            });
            el.addEventListener('drop', (e) => {
                e.preventDefault();
                el.classList.remove('drag-over');

                const draggedId = e.dataTransfer.getData('text/plain');
                const draggedEl = document.getElementById(draggedId);
                if (!draggedEl || draggedEl === el) return;

                // Swap positions in layout
                const fromKey = draggedEl.dataset.widgetKey;
                const toKey = el.dataset.widgetKey;

                const fromConfig = this.layout.widgets[fromKey];
                const toConfig = this.layout.widgets[toKey];

                if (fromConfig && toConfig) {
                    const tempX = fromConfig.x;
                    const tempY = fromConfig.y;
                    fromConfig.x = toConfig.x;
                    fromConfig.y = toConfig.y;
                    toConfig.x = tempX;
                    toConfig.y = tempY;

                    // Re-render to apply new positions
                    this.render();
                    this.autoSave();
                }
            });
        });
    }

    // ── Widget Catalog ──────────────────────────────────────────
    async loadCatalog() {
        let catalog = document.getElementById('widget-catalog');
        if (!catalog) {
            catalog = document.createElement('div');
            catalog.id = 'widget-catalog';
            catalog.className = 'widget-catalog fixed right-0 top-16 h-full w-80 bg-white shadow-xl border-l border-slate-200 z-40 overflow-y-auto transform transition-transform';
            document.body.appendChild(catalog);
        }

        catalog.classList.remove('hidden');

        const module = this.options.dashboardKey === 'main' ? '' : this.options.dashboardKey;
        const url = `${this.options.widgetApiBase}/definitions.php${module ? '?module=' + module : ''}`;

        try {
            const resp = await fetch(url);
            const result = await resp.json();

            if (!result.success) {
                catalog.innerHTML = '<div class="p-4 text-red-600">Failed to load widgets</div>';
                return;
            }

            const availableWidgets = result.widgets.filter(w => {
                return !this.layout.widgets[w.widget_key] ||
                    this.layout.widgets[w.widget_key].enabled === false;
            });

            // Group by category
            const categories = {};
            availableWidgets.forEach(w => {
                if (!categories[w.category]) categories[w.category] = [];
                categories[w.category].push(w);
            });

            let html = `
                <div class="p-4 border-b border-slate-200 flex justify-between items-center sticky top-0 bg-white z-10">
                    <h3 class="font-semibold text-slate-800">Widget Catalog</h3>
                    <button onclick="document.getElementById('widget-catalog').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="p-4">
            `;

            Object.entries(categories).forEach(([category, widgets]) => {
                html += `
                    <div class="mb-4">
                        <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">${category}</h4>
                        <div class="space-y-2">
                `;
                widgets.forEach(w => {
                    html += `
                        <div class="widget-catalog-item flex items-center gap-3 p-3 rounded-lg border border-slate-100 hover:bg-slate-50 cursor-pointer transition"
                             data-widget-key="${w.widget_key}"
                             data-definition='${JSON.stringify(w).replace(/'/g, '&#39;')}'>
                            <i class="${w.icon} text-indigo-500 text-lg w-6 text-center"></i>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-slate-700 truncate">${this.esc(w.name)}</p>
                                <p class="text-xs text-slate-400 truncate">${this.esc(w.description)}</p>
                            </div>
                            <button class="add-widget-btn text-indigo-600 hover:text-indigo-800 text-sm p-1" title="Add widget">
                                <i class="fas fa-plus-circle"></i>
                            </button>
                        </div>
                    `;
                });
                html += '</div></div>';
            });

            if (availableWidgets.length === 0) {
                html += '<div class="text-sm text-slate-400 text-center py-8">All widgets are already on your dashboard</div>';
            }

            html += '</div>';
            catalog.innerHTML = html;

            // Bind add buttons
            catalog.querySelectorAll('.add-widget-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const item = btn.closest('.widget-catalog-item');
                    const widgetKey = item.dataset.widgetKey;
                    const definition = JSON.parse(item.dataset.definition);
                    this.addWidget(widgetKey, definition);
                });
            });
        } catch (err) {
            console.error('Catalog load error:', err);
        }
    }

    // ── Utility ─────────────────────────────────────────────────
    showEmptyState() {
        const empty = document.createElement('div');
        empty.className = 'col-span-full flex flex-col items-center justify-center py-16 text-slate-400';
        empty.innerHTML = `
            <i class="fas fa-th-large text-4xl mb-3"></i>
            <p class="text-lg font-medium">Your dashboard is empty</p>
            <p class="text-sm">Click Customize to add widgets</p>
        `;
        this.grid.appendChild(empty);
    }

    showError(message) {
        const err = document.createElement('div');
        err.className = 'bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4';
        err.textContent = message;
        this.container.prepend(err);
        if (this.options.onError) this.options.onError(message);
    }

    renderWidgetError(widgetKey, config, error) {
        const widget = this.widgets[widgetKey];
        if (!widget) return;
        widget.body.innerHTML = `
            <div class="text-sm text-red-400 text-center py-4">
                <i class="fas fa-exclamation-circle mr-1"></i>
                ${error || 'Failed to load widget'}
            </div>
        `;
    }

    // ── Helpers ─────────────────────────────────────────────────
    esc(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    formatNum(num) {
        if (num === undefined || num === null) return '0';
        return Number(num).toLocaleString();
    }

    formatDate(dateStr) {
        if (!dateStr) return '';
        const d = new Date(dateStr.replace(' ', 'T'));
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }
}
