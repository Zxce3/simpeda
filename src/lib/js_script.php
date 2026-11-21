<?php

// Runs in <head>: applies the saved (or system) theme before first paint.
function getThemeCode()
{
    ?>
    <script nonce="<?= e(cspNonce()) ?>">
        (function () {
            var theme = null;
            try { theme = localStorage.getItem('theme'); } catch (e) {}
            if (theme !== 'dark' && theme !== 'light') {
                theme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <?php
}

function getJavascriptCode()
{
    ?>
    <script nonce="<?= e(cspNonce()) ?>">
(() => {
'use strict';

const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const csrf = ($('meta[name=csrf-token]') || {}).content || '';
const app = $('#app');
const page = app ? app.dataset.page : '';
const timers = [];

const themeButton = $('#theme-toggle');
if (themeButton) {
    themeButton.addEventListener('click', () => {
        const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        try { localStorage.setItem('theme', next); } catch (e) {}
        window.dispatchEvent(new Event('resize'));
    });
}

// ---- helpers -----------------------------------------------------------------------------

function toast(message, isError) {
    const box = $('#toasts');
    if (!box) return;
    const el = document.createElement('div');
    el.className = 'toast' + (isError ? ' error' : '');
    el.textContent = message;
    box.appendChild(el);
    setTimeout(() => el.remove(), isError ? 6000 : 3000);
}

async function api(method, url, body) {
    const options = { method, headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrf } };
    if (body !== undefined) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(body);
    }
    const res = await fetch(url, options);
    const data = await res.json().catch(() => ({}));
    if (res.status === 401 && !url.startsWith('/api/public')) {
        location.href = '/login';
        throw new Error('Signed out');
    }
    if (!res.ok) {
        const error = new Error(data.error || res.statusText);
        error.status = res.status;
        throw error;
    }
    return data;
}

function every(ms, fn) {
    timers.push(setInterval(() => { if (!document.hidden) fn(); }, ms));
}

const fmtBytes = (n, digits = 1) => {
    n = Number(n) || 0;
    const units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    let i = 0;
    while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
    return (i ? n.toFixed(digits) : Math.round(n)) + ' ' + units[i];
};
const fmtSpeed = (n) => fmtBytes(n) + '/s';
const fmtPct = (n) => (Number(n) || 0).toFixed(1) + '%';
const fmtUptime = (s) => {
    s = Number(s) || 0;
    const d = Math.floor(s / 86400), h = Math.floor((s % 86400) / 3600), m = Math.floor((s % 3600) / 60);
    return d ? d + 'd ' + h + 'h' : h ? h + 'h ' + m + 'm' : m + 'm';
};
const ago = (ts) => {
    if (!ts) return 'never';
    const d = Math.max(0, Math.floor(Date.now() / 1000) - ts);
    if (d < 5) return 'just now';
    if (d < 60) return d + 's ago';
    if (d < 3600) return Math.floor(d / 60) + 'm ago';
    if (d < 86400) return Math.floor(d / 3600) + 'h ago';
    return Math.floor(d / 86400) + 'd ago';
};
const flag = (cc) => /^[A-Za-z]{2}$/.test(cc || '')
    ? String.fromCodePoint(...[...cc.toUpperCase()].map((c) => 127397 + c.charCodeAt(0))) : '🌐';
const level = (v) => (v >= 90 ? 'bad' : v >= 75 ? 'warn' : '');
const meter = (name, v) => {
    v = Number(v) || 0;
    return '<div class="meter"><span class="name">' + name + '</span><div class="bar"><i class="' + level(v) + '" style="width:' + Math.min(100, v).toFixed(1) + '%"></i></div><span class="val">' + v.toFixed(0) + '%</span></div>';
};
const statusDot = (online) => '<span class="dot ' + (online ? 'on' : 'off') + '" title="' + (online ? 'Online' : 'Offline') + '"></span>';
const fail = (el, e) => { el.innerHTML = '<div class="empty card"><h2>Something went wrong</h2><p>' + esc(e.message) + '</p></div>'; };

function modal(title, bodyHtml, options = {}) {
    const dlg = document.createElement('dialog');
    dlg.innerHTML = '<form method="dialog"><h2>' + esc(title) + '</h2><div class="dlg-body">' + bodyHtml + '</div><p class="error-text hidden" role="alert"></p><div class="dialog-actions"><button type="button" data-close>' + esc(options.cancel || 'Close') + '</button>'
        + (options.onSubmit ? '<button class="primary" type="submit">' + esc(options.submit || 'Save') + '</button>' : '') + '</div></form>';
    document.body.appendChild(dlg);
    linkLabels(dlg);
    dlg.addEventListener('close', () => dlg.remove());
    $('[data-close]', dlg).addEventListener('click', () => dlg.close());
    const form = $('form', dlg);
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!options.onSubmit) return;
        const button = $('button[type=submit]', dlg);
        const errorBox = $('.error-text', dlg);
        button.disabled = true;
        errorBox.classList.add('hidden');
        try {
            if ((await options.onSubmit(form)) !== false) dlg.close();
        } catch (e) {
            errorBox.textContent = e.message;
            errorBox.classList.remove('hidden');
        } finally {
            button.disabled = false;
        }
    });
    dlg.showModal();
    return dlg;
}

function confirmBox(message, label, action) {
    modal('Are you sure?', '<p>' + esc(message) + '</p>', { submit: label, cancel: 'Cancel', onSubmit: async () => { await action(); } });
}

// Associates each <label> with the field that follows it (labels are written without ids).
function linkLabels(root) {
    $$('label:not(.check)', root).forEach((label) => {
        const field = label.nextElementSibling;
        if (field && /^(INPUT|SELECT|TEXTAREA)$/.test(field.tagName)) {
            if (!field.id) field.id = 'f' + Math.random().toString(36).slice(2, 9);
            label.htmlFor = field.id;
        }
    });
}

const formData = (form) => Object.fromEntries(new FormData(form));

// ---- charts ------------------------------------------------------------------------------

function niceMax(v) {
    if (v <= 0) return 1;
    const p = Math.pow(10, Math.floor(Math.log10(v)));
    const n = v / p;
    return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 5 ? 5 : 10) * p;
}

function drawChart(host, opts, data) {
    const points = data.points || [];
    host.innerHTML = '';
    if (!points.length) {
        host.innerHTML = '<div class="empty small">No data yet</div>';
        return;
    }
    const W = host.clientWidth || 400, H = 170, pad = { l: 44, r: 8, t: 8, b: 20 };
    const from = data.from, to = Math.max(data.to, from + 1);
    const values = points.flatMap((p) => opts.series.map((s) => Number(p[s.key]) || 0));
    const yMax = opts.max || niceMax(Math.max(...values) * 1.1);
    const x = (ts) => pad.l + ((ts - from) / (to - from)) * (W - pad.l - pad.r);
    const y = (v) => pad.t + (1 - Math.min(v, yMax) / yMax) * (H - pad.t - pad.b);
    const css = getComputedStyle(document.documentElement);
    const colors = opts.series.map((s) => css.getPropertyValue(s.color).trim() || '#3b6cf6');

    let svg = '<svg viewBox="0 0 ' + W + ' ' + H + '" role="img" aria-label="' + esc(opts.title) + '">';
    for (let i = 0; i <= 4; i++) {
        const v = (yMax * i) / 4, yy = y(v);
        svg += '<line class="grid-line" x1="' + pad.l + '" x2="' + (W - pad.r) + '" y1="' + yy + '" y2="' + yy + '"/>'
            + '<text class="axis" x="' + (pad.l - 6) + '" y="' + (yy + 3) + '" text-anchor="end">' + esc(opts.fmt(v)) + '</text>';
    }
    const span = to - from;
    for (let i = 0; i <= 4; i++) {
        const ts = from + (span * i) / 4, d = new Date(ts * 1000);
        const label = span > 172800 ? d.toLocaleDateString([], { month: 'short', day: 'numeric' }) : d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        svg += '<text class="axis" x="' + x(ts) + '" y="' + (H - 4) + '" text-anchor="' + (i === 0 ? 'start' : i === 4 ? 'end' : 'middle') + '">' + label + '</text>';
    }
    opts.series.forEach((s, i) => {
        const line = points.map((p, j) => (j ? 'L' : 'M') + x(p.ts).toFixed(1) + ' ' + y(Number(p[s.key]) || 0).toFixed(1)).join(' ');
        if (opts.area && i === 0) {
            svg += '<path d="' + line + ' L' + x(points[points.length - 1].ts).toFixed(1) + ' ' + y(0) + ' L' + x(points[0].ts).toFixed(1) + ' ' + y(0) + ' Z" fill="' + colors[i] + '" opacity=".14"/>';
        }
        svg += '<path d="' + line + '" fill="none" stroke="' + colors[i] + '" stroke-width="1.8" stroke-linejoin="round"/>';
    });
    svg += '<line class="cursor" y1="' + pad.t + '" y2="' + (H - pad.b) + '" stroke="' + colors[0] + '" stroke-dasharray="3 3" opacity="0"/></svg>';
    host.innerHTML = svg + '<div class="chart-tip hidden"></div>';

    const el = $('svg', host), tip = $('.chart-tip', host), cursor = $('.cursor', host);
    const move = (event) => {
        const rect = el.getBoundingClientRect();
        const clientX = (event.touches ? event.touches[0].clientX : event.clientX) - rect.left;
        const ts = from + ((clientX / rect.width * W - pad.l) / (W - pad.l - pad.r)) * (to - from);
        let best = points[0];
        for (const p of points) if (Math.abs(p.ts - ts) < Math.abs(best.ts - ts)) best = p;
        const px = x(best.ts);
        cursor.setAttribute('x1', px); cursor.setAttribute('x2', px); cursor.setAttribute('opacity', '1');
        tip.innerHTML = '<div class="muted">' + new Date(best.ts * 1000).toLocaleString() + '</div>'
            + opts.series.map((s, i) => '<div><i style="display:inline-block;width:8px;height:8px;border-radius:2px;margin-right:6px;background:' + colors[i] + '"></i>' + esc(s.name) + ': <b>' + esc(opts.fmt(Number(best[s.key]) || 0)) + '</b></div>').join('');
        tip.classList.remove('hidden');
        const left = Math.min(Math.max(px / W * rect.width + 12, 0), rect.width - tip.offsetWidth);
        tip.style.left = left + 'px';
        tip.style.top = '28px';
    };
    const leave = () => { cursor.setAttribute('opacity', '0'); tip.classList.add('hidden'); };
    el.addEventListener('mousemove', move);
    el.addEventListener('touchmove', move, { passive: true });
    el.addEventListener('mouseleave', leave);
    el.addEventListener('touchend', leave);
}

// Charts re-render on resize (and theme change) from the data they were last given.
const charts = [];
function chart(host, opts) {
    const entry = { host, opts, data: null, draw() { if (this.data) drawChart(host, opts, this.data); } };
    charts.push(entry);
    return entry;
}
let resizeTimer;
window.addEventListener('resize', () => { clearTimeout(resizeTimer); resizeTimer = setTimeout(() => charts.forEach((c) => c.draw()), 120); });

// ---- dashboard ---------------------------------------------------------------------------

function serverCard(s) {
    const l = s.live || {}, host = s.host || {};
    const sub = [host.platform, host.arch].filter(Boolean).join(' · ') || (s.note || '');
    return '<a class="card server' + (s.online ? '' : ' offline') + '" href="/server?id=' + encodeURIComponent(s.id) + '">'
        + '<div class="server-head"><span class="flag">' + flag(s.country_code) + '</span><div class="server-title"><div class="server-name">' + esc(s.name) + '</div><div class="server-sub">' + esc(sub) + '</div></div>' + statusDot(s.online) + '</div>'
        + meter('CPU', l.cpu) + meter('MEM', l.mem) + meter('DISK', l.disk)
        + '<div class="kv"><span>Download</span><span>' + fmtSpeed(l.rx_speed) + '</span><span>Upload</span><span>' + fmtSpeed(l.tx_speed) + '</span>'
        + '<span>Load</span><span>' + (Number(l.load1) || 0).toFixed(2) + '</span>'
        + '<span>Uptime</span><span>' + (s.online ? fmtUptime(l.uptime) : 'seen ' + ago(s.last_seen)) + '</span>'
        + '<span>Traffic</span><span>↓ ' + fmtBytes(l.rx_total) + ' · ↑ ' + fmtBytes(l.tx_total) + '</span></div></a>';
}

function serverRow(s) {
    const l = s.live || {};
    const bar = (v) => '<div class="bar"><i class="' + level(v) + '" style="width:' + Math.min(100, Number(v) || 0) + '%"></i></div>';
    return '<tr class="clickable" data-href="/server?id=' + encodeURIComponent(s.id) + '"><td>' + statusDot(s.online) + ' ' + flag(s.country_code) + ' <b>' + esc(s.name) + '</b></td>'
        + '<td>' + bar(l.cpu) + '<span class="small muted">' + fmtPct(l.cpu) + '</span></td><td>' + bar(l.mem) + '<span class="small muted">' + fmtPct(l.mem) + '</span></td><td>' + bar(l.disk) + '<span class="small muted">' + fmtPct(l.disk) + '</span></td>'
        + '<td class="nowrap">↓ ' + fmtSpeed(l.rx_speed) + '</td><td class="nowrap">↑ ' + fmtSpeed(l.tx_speed) + '</td><td>' + (Number(l.load1) || 0).toFixed(2) + '</td><td>' + (s.online ? fmtUptime(l.uptime) : ago(s.last_seen)) + '</td></tr>';
}

function dashboardPage() {
    const read = (key, fallback) => { try { return localStorage.getItem(key) || fallback; } catch (e) { return fallback; } };
    const state = { servers: null, filter: 'all', q: '', view: read('view', 'cards') };
    app.innerHTML = '<div class="page-head"><h1>Servers</h1><div class="toolbar">'
        + '<input class="search" type="search" id="q" placeholder="Search servers" aria-label="Search servers">'
        + '<div class="seg" id="filter"><button data-v="all" class="on">All</button><button data-v="online">Online</button><button data-v="offline">Offline</button></div>'
        + '<div class="seg" id="view"><button data-v="cards" title="Cards">▦</button><button data-v="table" title="Table">☰</button></div></div></div>'
        + '<div class="stats" id="stats"></div><div id="list"><div class="grid"><div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div></div></div>';
    const list = $('#list'), stats = $('#stats');

    function render() {
        if (state.servers === null) return;
        $$('#filter button').forEach((b) => b.classList.toggle('on', b.dataset.v === state.filter));
        $$('#view button').forEach((b) => b.classList.toggle('on', b.dataset.v === state.view));
        const all = state.servers, online = all.filter((s) => s.online);
        const sum = (key) => online.reduce((t, s) => t + (Number((s.live || {})[key]) || 0), 0);
        const total = (key) => all.reduce((t, s) => t + (Number((s.live || {})[key]) || 0), 0);
        stats.innerHTML = [
            ['Servers', all.length], ['Online', online.length + '<small> / ' + all.length + '</small>'],
            ['Offline', all.length - online.length], ['Download', fmtSpeed(sum('rx_speed'))], ['Upload', fmtSpeed(sum('tx_speed'))],
            ['Total traffic', '<small>↓</small> ' + fmtBytes(total('rx_total')) + ' <small>↑</small> ' + fmtBytes(total('tx_total'))],
        ].map(([label, value]) => '<div class="stat"><div class="label">' + label + '</div><div class="value">' + value + '</div></div>').join('');

        const q = state.q.toLowerCase();
        const shown = all.filter((s) => (state.filter === 'all' || (state.filter === 'online') === s.online)
            && (!q || (s.name + ' ' + (s.note || '') + ' ' + ((s.host || {}).platform || '')).toLowerCase().includes(q)));
        if (!all.length) {
            list.innerHTML = '<div class="card empty"><h2>No servers yet</h2><p>Add a server in Settings, then run the agent on it. It takes about a minute.</p><a class="btn primary" href="/settings">Add a server</a></div>';
        } else if (!shown.length) {
            list.innerHTML = '<div class="card empty">No servers match.</div>';
        } else if (state.view === 'table') {
            list.innerHTML = '<div class="card table-wrap"><table><thead><tr><th>Server</th><th>CPU</th><th>Memory</th><th>Disk</th><th>Down</th><th>Up</th><th>Load</th><th>Uptime</th></tr></thead><tbody>' + shown.map(serverRow).join('') + '</tbody></table></div>';
            $$('tr[data-href]', list).forEach((tr) => tr.addEventListener('click', () => { location.href = tr.dataset.href; }));
        } else {
            list.innerHTML = '<div class="grid">' + shown.map(serverCard).join('') + '</div>';
        }
    }

    async function load() {
        try {
            state.servers = (await api('GET', '/api/servers')).servers;
            render();
        } catch (e) { if (state.servers === null) fail(list, e); }
    }

    $('#q').addEventListener('input', (e) => { state.q = e.target.value; render(); });
    $('#filter').addEventListener('click', (e) => { if (e.target.dataset.v) { state.filter = e.target.dataset.v; render(); } });
    $('#view').addEventListener('click', (e) => {
        if (!e.target.dataset.v) return;
        state.view = e.target.dataset.v;
        try { localStorage.setItem('view', state.view); } catch (err) {}
        render();
    });
    load();
    every(5000, load);
}

// ---- server detail -----------------------------------------------------------------------

function serverPage() {
    const id = new URLSearchParams(location.search).get('id') || '';
    let range = '1h';
    app.innerHTML = '<div class="page-head"><div><a href="/dashboard" class="small">← All servers</a><h1 id="title">Server</h1></div><div class="toolbar"><span id="status"></span></div></div>'
        + '<div class="stats" id="tiles"></div>'
        + '<div class="page-head"><h2>History</h2><div class="seg" id="range"><button data-v="1h" class="on">1h</button><button data-v="6h">6h</button><button data-v="24h">24h</button><button data-v="7d">7d</button></div></div>'
        + '<div class="charts" id="charts"></div><div class="card" style="margin-top:16px"><div class="card-head"><h2>Host</h2></div><div class="info-grid" id="info"></div></div>';

    const defs = [
        { id: 'cpu', title: 'CPU', series: [{ key: 'cpu', name: 'CPU', color: '--accent' }], max: 100, area: true, fmt: (v) => v.toFixed(0) + '%' },
        { id: 'mem', title: 'Memory', series: [{ key: 'mem', name: 'Memory', color: '--good' }, { key: 'swap', name: 'Swap', color: '--warn' }], max: 100, area: true, fmt: (v) => v.toFixed(0) + '%' },
        { id: 'net', title: 'Network', series: [{ key: 'rx_speed', name: 'Down', color: '--accent' }, { key: 'tx_speed', name: 'Up', color: '--good' }], area: true, fmt: (v) => fmtSpeed(v) },
        { id: 'load', title: 'Load (1 min)', series: [{ key: 'load1', name: 'Load', color: '--warn' }], area: true, fmt: (v) => v.toFixed(2) },
        { id: 'disk', title: 'Disk', series: [{ key: 'disk', name: 'Disk', color: '--bad' }], max: 100, area: true, fmt: (v) => v.toFixed(0) + '%' },
    ];
    $('#charts').innerHTML = defs.map((d) => '<div class="card chart"><h3>' + d.title + '<span class="legend">' + d.series.map((s) => '<span><i style="background:var(' + s.color + ')"></i>' + s.name + '</span>').join('') + '</span></h3><div id="c-' + d.id + '"></div></div>').join('');
    const live = defs.map((d) => chart($('#c-' + d.id), Object.assign({}, d)));

    function renderLive(s) {
        const l = s.live || {}, h = s.host || {};
        document.title = s.name + ' · SIMPEDA';
        $('#title').innerHTML = flag(s.country_code) + ' ' + esc(s.name);
        $('#status').innerHTML = (s.online ? '<span class="pill good">Online</span>' : '<span class="pill bad">Offline</span>') + ' <span class="muted small">seen ' + ago(s.last_seen) + '</span>';
        const tile = (label, value) => '<div class="stat"><div class="label">' + label + '</div><div class="value">' + value + '</div></div>';
        $('#tiles').innerHTML = tile('CPU', fmtPct(l.cpu)) + tile('Memory', fmtPct(l.mem) + '<small> ' + fmtBytes(l.mem_used) + ' / ' + fmtBytes(l.mem_total) + '</small>')
            + tile('Swap', fmtPct(l.swap)) + tile('Disk', fmtPct(l.disk) + '<small> ' + fmtBytes(l.disk_used) + ' / ' + fmtBytes(l.disk_total) + '</small>')
            + tile('Load', (Number(l.load1) || 0).toFixed(2) + '<small> ' + (Number(l.load5) || 0).toFixed(2) + ' · ' + (Number(l.load15) || 0).toFixed(2) + '</small>')
            + tile('Download', fmtSpeed(l.rx_speed)) + tile('Upload', fmtSpeed(l.tx_speed)) + tile('Processes', Math.round(l.procs || 0)) + tile('Uptime', fmtUptime(l.uptime));
        const rows = [
            ['Hostname', h.hostname], ['OS', h.platform], ['Kernel', h.kernel], ['Architecture', h.arch], ['CPU', h.cpu_model],
            ['Cores', h.cores], ['Memory', h.mem_total ? fmtBytes(h.mem_total) : ''], ['Swap', h.swap_total ? fmtBytes(h.swap_total) : ''], ['Disk', h.disk_total ? fmtBytes(h.disk_total) : ''],
            ['Booted', h.boot_time ? new Date(h.boot_time * 1000).toLocaleString() : ''], ['Agent', h.agent], ['Traffic', '↓ ' + fmtBytes(l.rx_total) + ' · ↑ ' + fmtBytes(l.tx_total)], ['Note', s.note],
        ].filter(([, v]) => v);
        $('#info').innerHTML = rows.map(([k, v]) => '<div><span>' + k + '</span><span>' + esc(v) + '</span></div>').join('');
    }

    async function loadLive() {
        try { renderLive(await api('GET', '/api/servers/' + encodeURIComponent(id))); }
        catch (e) { if (e.status === 404) app.innerHTML = '<div class="card empty"><h2>Server not found</h2><a class="btn primary" href="/dashboard">Back to servers</a></div>'; }
    }
    async function loadHistory() {
        try {
            const data = await api('GET', '/api/servers/' + encodeURIComponent(id) + '/history?range=' + range);
            live.forEach((c) => { c.data = data; c.draw(); });
        } catch (e) { toast(e.message, true); }
    }
    $('#range').addEventListener('click', (e) => {
        if (!e.target.dataset.v) return;
        range = e.target.dataset.v;
        $$('#range button').forEach((b) => b.classList.toggle('on', b.dataset.v === range));
        loadHistory();
    });
    loadLive(); loadHistory();
    every(5000, loadLive);
    every(30000, loadHistory);
}

// ---- monitors ----------------------------------------------------------------------------

function monitorStatus(m) {
    if (!m.enabled) return '<span class="pill">Paused</span>';
    if (!m.last_run) return '<span class="pill">Pending</span>';
    return m.last_ok ? '<span class="pill good">Up</span>' : '<span class="pill bad">Down</span>';
}

function ticks(recent, count = 60) {
    const cells = recent.slice(-count).map((r) => '<i class="' + (r.ok ? 'ok' : 'fail') + '" title="' + esc(new Date(r.ts * 1000).toLocaleString() + (r.ok ? ' · ' + r.latency + ' ms' : ' · failed')) + '"></i>');
    while (cells.length < count) cells.unshift('<i></i>');
    return '<div class="ticks">' + cells.join('') + '</div>';
}

function monitorForm(m = {}) {
    return '<label>Name</label><input type="text" name="name" required maxlength="64" value="' + esc(m.name) + '">'
        + '<div class="row"><div><label>Type</label><select name="type"><option value="http">HTTP(S)</option><option value="tcp">TCP port</option><option value="ping">Ping</option></select></div>'
        + '<div><label>Check every (s)</label><input type="number" name="interval" min="10" max="86400" value="' + esc(m.interval || 60) + '"></div></div>'
        + '<label>Target</label><input type="text" name="target" required placeholder="https://example.com  |  host:port  |  hostname" value="' + esc(m.target) + '">'
        + '<div class="row"><div><label>Timeout (s)</label><input type="number" name="timeout" min="1" max="60" value="' + esc(m.timeout || 10) + '"></div>'
        + '<div><label>Expected HTTP status (0 = any 2xx/3xx)</label><input type="number" name="expect_status" min="0" max="599" value="' + esc(m.expect_status || 0) + '"></div></div>'
        + '<label class="check"><input type="checkbox" name="enabled"' + (m.enabled === false ? '' : ' checked') + '> Enabled</label>'
        + '<label class="check"><input type="checkbox" name="public"' + (m.public ? ' checked' : '') + '> Show on the public status page</label>';
}

function monitorPayload(form) {
    const f = formData(form);
    return { name: f.name, type: f.type, target: f.target, interval: +f.interval, timeout: +f.timeout, expect_status: +f.expect_status, enabled: !!f.enabled, public: !!f.public };
}

function monitorsPage() {
    let monitors = null;
    app.innerHTML = '<div class="page-head"><h1>Monitors</h1><button class="primary" id="add">+ Add monitor</button></div><div id="list"><div class="skeleton"></div></div>';
    const list = $('#list');

    function render() {
        if (!monitors.length) {
            list.innerHTML = '<div class="card empty"><h2>No monitors yet</h2><p>Check websites, TCP ports or hosts and get alerted when they go down.</p></div>';
            return;
        }
        list.innerHTML = '<div class="grid" style="grid-template-columns:repeat(auto-fill,minmax(360px,1fr))">' + monitors.map((m) =>
            '<div class="card monitor" data-id="' + esc(m.id) + '"><div class="monitor-head"><div class="grow"><b>' + esc(m.name) + '</b> <span class="pill">' + esc(m.type) + '</span>'
            + '<div class="small muted" style="overflow-wrap:anywhere">' + esc(m.target) + '</div></div>' + monitorStatus(m) + '</div>'
            + ticks(m.recent || [])
            + '<div class="kv" style="margin-top:0;border:0;padding:0"><span>Latency</span><span>' + (m.last_run ? m.last_latency + ' ms' : '–') + '</span><span>Uptime 24h</span><span>' + (m.uptime24 === null || m.uptime24 === undefined ? '–' : m.uptime24 + '%') + '</span></div>'
            + '<div class="toolbar"><button data-act="chart">Chart</button><button data-act="toggle">' + (m.enabled ? 'Pause' : 'Resume') + '</button><button data-act="edit">Edit</button><button class="danger" data-act="delete">Delete</button></div></div>').join('') + '</div>';
    }

    async function load() {
        try { monitors = (await api('GET', '/api/monitors')).monitors; render(); }
        catch (e) { if (monitors === null) fail(list, e); }
    }

    function edit(m) {
        const dlg = modal(m ? 'Edit monitor' : 'Add monitor', monitorForm(m || {}), {
            submit: m ? 'Save' : 'Add', cancel: 'Cancel',
            onSubmit: async (form) => {
                const payload = monitorPayload(form);
                if (m) await api('PATCH', '/api/monitors/' + m.id, payload); else await api('POST', '/api/monitors', payload);
                toast('Saved');
                load();
            },
        });
        if (m) $('select[name=type]', dlg).value = m.type;
    }

    function showChart(m) {
        let range = '1h';
        const dlg = modal(m.name, '<div class="seg" id="mrange" style="margin-bottom:8px"><button data-v="1h" class="on">1h</button><button data-v="6h">6h</button><button data-v="24h">24h</button><button data-v="7d">7d</button></div><div class="chart"><div id="mchart"></div></div>');
        dlg.style.width = 'min(720px, calc(100vw - 24px))';
        const c = chart($('#mchart', dlg), { title: m.name + ' latency', series: [{ key: 'latency', name: 'Latency', color: '--accent' }], area: true, fmt: (v) => (v < 10 ? v.toFixed(2) : v.toFixed(0)) + ' ms' });
        const draw = async () => {
            const seconds = { '1h': 3600, '6h': 21600, '24h': 86400, '7d': 604800 }[range];
            const to = Math.floor(Date.now() / 1000);
            const rows = (await api('GET', '/api/monitors/' + m.id + '/results?range=' + range)).results;
            c.data = { from: to - seconds, to, points: rows.filter((r) => r.ok).map((r) => ({ ts: r.ts, latency: r.latency })) };
            c.draw();
        };
        $('#mrange', dlg).addEventListener('click', (e) => {
            if (!e.target.dataset.v) return;
            range = e.target.dataset.v;
            $$('#mrange button', dlg).forEach((b) => b.classList.toggle('on', b.dataset.v === range));
            draw().catch((err) => toast(err.message, true));
        });
        draw().catch((err) => toast(err.message, true));
    }

    $('#add').addEventListener('click', () => edit(null));
    list.addEventListener('click', async (event) => {
        const button = event.target.closest('button[data-act]');
        if (!button) return;
        const m = monitors.find((x) => x.id === button.closest('.monitor').dataset.id);
        try {
            if (button.dataset.act === 'edit') edit(m);
            else if (button.dataset.act === 'chart') showChart(m);
            else if (button.dataset.act === 'toggle') { await api('PATCH', '/api/monitors/' + m.id, { enabled: !m.enabled }); load(); }
            else if (button.dataset.act === 'delete') confirmBox('Delete monitor "' + m.name + '" and its history?', 'Delete', async () => { await api('DELETE', '/api/monitors/' + m.id); toast('Deleted'); load(); });
        } catch (e) { toast(e.message, true); }
    });
    load();
    every(10000, load);
}

// ---- settings ----------------------------------------------------------------------------

function serverForm(s = {}) {
    return '<label>Name</label><input type="text" name="name" required maxlength="64" value="' + esc(s.name) + '">'
        + '<div class="row"><div><label>Country code (flag)</label><input type="text" name="country_code" maxlength="2" placeholder="DE" value="' + esc(s.country_code) + '"></div>'
        + '<div><label>Order</label><input type="number" name="sort" value="' + esc(s.sort || 0) + '"></div></div>'
        + '<label>Note</label><input type="text" name="note" maxlength="200" value="' + esc(s.note) + '">'
        + '<label class="check"><input type="checkbox" name="public"' + (s.public ? ' checked' : '') + '> Show on the public status page</label>';
}

function showToken(name, token) {
    const origin = location.origin;
    const one = 'curl -fsSL ' + origin + '/install/agent.sh | sudo bash -s -- --token ' + token;
    const manual = 'curl -fsSL ' + origin + '/download/agent -o simpeda.php\nphp simpeda.php agent --url=' + origin + ' --token=' + token;
    const unit = '[Unit]\nDescription=SIMPEDA agent\nAfter=network-online.target\n\n[Service]\nExecStart=/usr/bin/php /opt/simpeda/simpeda.php agent --url=' + origin + ' --token=' + token + '\nRestart=always\nRestartSec=5\n\n[Install]\nWantedBy=multi-user.target';
    modal('Install the agent on ' + name, '<p>This token is shown <b>once</b>. On the server you want to monitor (Linux), run as root:</p><pre><code>' + esc(one) + '</code></pre>'
        + '<p class="muted small">The script installs PHP if needed, downloads the agent (one small file, no database), tests the token and starts a systemd service. Read it first: <code>curl -fsSL ' + esc(origin) + '/install/agent.sh | less</code></p>'
        + '<details><summary class="small">Manual install instead</summary><pre><code>' + esc(manual) + '</code></pre>'
        + '<p class="muted small">Keep it running with this unit (<code>/etc/systemd/system/simpeda-agent.service</code>):</p><pre><code>' + esc(unit) + '</code></pre></details>');
}

function settingsPage() {
    app.innerHTML = '<div class="page-head"><h1>Settings</h1></div><div id="content"><div class="skeleton"></div></div>';
    const content = $('#content');
    let servers = [], settings = null, alerts = [];

    function render() {
        const a = settings.alerts, n = settings.notify;
        content.innerHTML =
            '<div class="card"><div class="card-head"><h2>Servers</h2><button class="primary" id="add-server">+ Add server</button></div>'
            + (servers.length ? '<div class="table-wrap"><table><thead><tr><th>Name</th><th>Status</th><th>Last seen</th><th>Public</th><th></th></tr></thead><tbody>' + servers.map((s) =>
                '<tr data-id="' + esc(s.id) + '"><td>' + flag(s.country_code) + ' <b>' + esc(s.name) + '</b></td><td>' + (s.last_seen ? (s.online ? '<span class="pill good">Online</span>' : '<span class="pill bad">Offline</span>') : '<span class="pill">Waiting</span>') + '</td><td>' + ago(s.last_seen) + '</td><td>' + (s.public ? 'Yes' : 'No')
                + '</td><td class="actions"><button data-act="edit">Edit</button> <button data-act="token">New token</button> <button class="danger" data-act="delete">Delete</button></td></tr>').join('') + '</tbody></table></div>'
                : '<p class="muted">No servers yet. Add one to get an install command for the agent.</p>')
            + '</div>'
            + '<form class="card" id="public-form"><div class="card-head"><h2>Public status page</h2><a href="/status" class="small">Open /status →</a></div>'
            + '<label class="check"><input type="checkbox" name="public_status"' + (settings.public_status ? ' checked' : '') + '> Enable the public status page</label>'
            + '<p class="muted small">Shows servers and monitors you marked as public: name, status, CPU, memory, disk, network and uptime. Never processes, IPs or logs.</p>'
            + '<label>Title</label><input type="text" name="site_title" maxlength="80" value="' + esc(settings.site_title) + '"><div class="dialog-actions"><button class="primary" type="submit">Save</button></div></form>'
            + '<form class="card" id="alerts-form"><div class="card-head"><h2>Alert thresholds</h2></div><div class="row">'
            + '<div><label>Offline after (s)</label><input type="number" name="offline_after" min="15" value="' + a.offline_after + '"></div>'
            + '<div><label>CPU % (0 = off)</label><input type="number" name="cpu" min="0" max="100" value="' + a.cpu + '"></div>'
            + '<div><label>Memory % (0 = off)</label><input type="number" name="mem" min="0" max="100" value="' + a.mem + '"></div>'
            + '<div><label>Disk % (0 = off)</label><input type="number" name="disk" min="0" max="100" value="' + a.disk + '"></div>'
            + '<div><label>Sustained for (s)</label><input type="number" name="sustain" min="0" value="' + a.sustain + '"></div>'
            + '<div><label>Monitor failures</label><input type="number" name="monitor_fails" min="1" value="' + a.monitor_fails + '"></div></div>'
            + '<p class="muted small">Alerts are evaluated by the worker (<code>php index.php worker</code>), which must be running.</p><div class="dialog-actions"><button class="primary" type="submit">Save</button></div></form>'
            + '<form class="card" id="notify-form"><div class="card-head"><h2>Notifications</h2></div><label>Channel</label><select name="type"><option value="">Disabled</option><option value="webhook">Generic webhook (JSON)</option><option value="discord">Discord webhook</option><option value="telegram">Telegram bot</option></select>'
            + '<div id="notify-url"><label>Webhook URL</label><input type="text" name="url" autocomplete="off" value="' + esc(n.url) + '"></div>'
            + '<div id="notify-tg" class="row"><div><label>Bot token</label><input type="text" name="token" autocomplete="off" value="' + esc(n.token) + '"></div><div><label>Chat ID</label><input type="text" name="chat_id" value="' + esc(n.chat_id) + '"></div></div>'
            + '<div class="dialog-actions"><button type="button" id="test-notify">Send test</button><button class="primary" type="submit">Save</button></div></form>'
            + '<div class="card"><div class="card-head"><h2>Recent alerts</h2></div>' + (alerts.length ? '<div class="table-wrap"><table><thead><tr><th>When</th><th>Alert</th><th>State</th></tr></thead><tbody>' + alerts.map((e) =>
                '<tr><td class="nowrap">' + esc(new Date(e.ts * 1000).toLocaleString()) + '</td><td style="white-space:normal">' + esc(e.message) + '</td><td>' + (e.resolved ? '<span class="pill good">Resolved</span>' : '<span class="pill bad">Open</span>') + '</td></tr>').join('') + '</tbody></table></div>' : '<p class="muted">No alerts yet.</p>') + '</div>'
            + '<p class="muted small" style="margin-top:16px">SIMPEDA ' + esc(settings.version || '') + ' · history kept for ' + settings.retention_days + ' days (SIMPEDA_RETENTION_DAYS)</p>';
        $('select[name=type]', content).value = n.type;
        linkLabels(content);
        syncNotify();
    }

    function syncNotify() {
        const type = $('#notify-form select', content).value;
        $('#notify-url', content).classList.toggle('hidden', type !== 'webhook' && type !== 'discord');
        $('#notify-tg', content).classList.toggle('hidden', type !== 'telegram');
    }

    async function load() {
        try {
            const [s, cfg, al, session] = await Promise.all([api('GET', '/api/servers'), api('GET', '/api/settings'), api('GET', '/api/alerts'), api('GET', '/api/session')]);
            servers = s.servers; settings = cfg; settings.version = session.version; alerts = al.alerts;
            render();
        } catch (e) { fail(content, e); }
    }

    async function save(body, message) {
        try { settings = Object.assign(await api('PATCH', '/api/settings', body), { version: settings.version }); toast(message || 'Saved'); render(); }
        catch (e) { toast(e.message, true); }
    }

    content.addEventListener('change', (e) => { if (e.target.matches('#notify-form select')) syncNotify(); });
    content.addEventListener('submit', (event) => {
        event.preventDefault();
        const f = formData(event.target);
        if (event.target.id === 'public-form') save({ public_status: !!f.public_status, site_title: f.site_title });
        if (event.target.id === 'alerts-form') save({ alerts: f });
        if (event.target.id === 'notify-form') save({ notify: { type: f.type, url: f.url || '', token: f.token || '', chat_id: f.chat_id || '' } });
    });
    content.addEventListener('click', async (event) => {
        if (event.target.id === 'test-notify') {
            try { await api('POST', '/api/settings/test', {}); toast('Test notification sent'); } catch (e) { toast('Could not send: save the channel first, then check the URL/token', true); }
            return;
        }
        if (event.target.id === 'add-server') {
            modal('Add server', serverForm(), { submit: 'Create', cancel: 'Cancel', onSubmit: async (form) => {
                const f = formData(form);
                const res = await api('POST', '/api/servers', { name: f.name, country_code: f.country_code, note: f.note, sort: +f.sort, public: !!f.public });
                await load();
                showToken(f.name, res.token);
            } });
            return;
        }
        const button = event.target.closest('button[data-act]');
        if (!button) return;
        const s = servers.find((x) => x.id === button.closest('tr').dataset.id);
        if (button.dataset.act === 'edit') {
            modal('Edit server', serverForm(s), { submit: 'Save', cancel: 'Cancel', onSubmit: async (form) => {
                const f = formData(form);
                await api('PATCH', '/api/servers/' + s.id, { name: f.name, country_code: f.country_code, note: f.note, sort: +f.sort, public: !!f.public });
                toast('Saved'); load();
            } });
        } else if (button.dataset.act === 'token') {
            confirmBox('Generate a new token for "' + s.name + '"? The old token stops working immediately.', 'Generate', async () => { const res = await api('POST', '/api/servers/' + s.id + '/token', {}); showToken(s.name, res.token); });
        } else if (button.dataset.act === 'delete') {
            confirmBox('Delete "' + s.name + '" and all its history?', 'Delete', async () => { await api('DELETE', '/api/servers/' + s.id); toast('Deleted'); load(); });
        }
    });
    load();
    every(15000, async () => { try { servers = (await api('GET', '/api/servers')).servers; if (!$('dialog')) render(); } catch (e) {} });
}

// ---- public status -----------------------------------------------------------------------

function statusPage() {
    app.innerHTML = '<div id="status"><div class="skeleton"></div></div>';
    const box = $('#status');
    async function load() {
        try {
            const d = await api('GET', '/api/public');
            const down = d.servers.filter((s) => !s.online).length + d.monitors.filter((m) => m.checked && !m.ok).length;
            const cards = d.servers.map((s) => {
                const l = s.live || {};
                return '<div class="card server' + (s.online ? '' : ' offline') + '"><div class="server-head"><span class="flag">' + flag(s.country_code) + '</span><div class="server-title"><div class="server-name">' + esc(s.name) + '</div><div class="server-sub">' + esc([(s.host || {}).platform, (s.host || {}).arch].filter(Boolean).join(' · ')) + '</div></div>' + statusDot(s.online) + '</div>'
                    + meter('CPU', l.cpu) + meter('MEM', l.mem) + meter('DISK', l.disk)
                    + '<div class="kv"><span>Download</span><span>' + fmtSpeed(l.rx_speed) + '</span><span>Upload</span><span>' + fmtSpeed(l.tx_speed) + '</span><span>Uptime</span><span>' + (s.online ? fmtUptime(l.uptime) : 'offline') + '</span></div></div>';
            }).join('');
            const mons = d.monitors.map((m) => '<div class="card monitor"><div class="monitor-head"><div class="grow"><b>' + esc(m.name) + '</b></div>'
                + (!m.checked ? '<span class="pill">Pending</span>' : m.ok ? '<span class="pill good">Up</span>' : '<span class="pill bad">Down</span>') + '</div>' + ticks(m.recent || [])
                + '<div class="kv" style="margin-top:0;border:0;padding:0"><span>Uptime 24h</span><span>' + (m.uptime24 === null ? '–' : m.uptime24 + '%') + '</span><span>Latency</span><span>' + (m.checked ? m.latency + ' ms' : '–') + '</span></div></div>').join('');
            box.innerHTML = '<div class="page-head"><h1>' + esc(d.title) + '</h1><span class="muted small">Updated ' + new Date(d.time * 1000).toLocaleTimeString() + '</span></div>'
                + '<div class="banner ' + (down ? 'bad' : 'good') + '">' + (!d.servers.length && !d.monitors.length ? 'Nothing is published yet' : down ? down + ' ' + (down === 1 ? 'service has' : 'services have') + ' problems' : 'All systems operational') + '</div>'
                + (d.servers.length ? '<h2 style="margin:20px 0 10px">Servers</h2><div class="grid">' + cards + '</div>' : '')
                + (d.monitors.length ? '<h2 style="margin:20px 0 10px">Services</h2><div class="grid">' + mons + '</div>' : '');
        } catch (e) {
            box.innerHTML = e.status === 404
                ? '<div class="card empty"><h2>Status page is not enabled</h2><p>An administrator can turn it on in Settings.</p><a class="btn primary" href="/login">Sign in</a></div>'
                : '<div class="card empty"><h2>Status unavailable</h2><p>' + esc(e.message) + '</p></div>';
        }
    }
    load();
    every(15000, load);
}

const pages = { dashboard: dashboardPage, server: serverPage, monitors: monitorsPage, settings: settingsPage, status: statusPage };
if (pages[page]) pages[page]();
})();
    </script>
    <?php
}
?>
