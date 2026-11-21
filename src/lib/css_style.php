<?php

function getCssStyle()
{
    ?>
    <style>
        :root {
            --bg: #f4f6fa;
            --surface: #ffffff;
            --surface-2: #f0f3f9;
            --border: #e2e7f0;
            --text: #1d2433;
            --muted: #6b7690;
            --accent: #3b6cf6;
            --accent-text: #ffffff;
            --good: #16a34a;
            --warn: #d97706;
            --bad: #dc2626;
            --track: #e6eaf2;
            --shadow: 0 1px 2px rgba(20, 30, 60, .06), 0 4px 14px rgba(20, 30, 60, .05);
            --radius: 12px;
            color-scheme: light;
        }

        [data-theme="dark"] {
            --bg: #0f1420;
            --surface: #171d2c;
            --surface-2: #1d2539;
            --border: #28324a;
            --text: #e6eaf5;
            --muted: #8d99b5;
            --accent: #6d8cff;
            --accent-text: #0b1020;
            --good: #34d399;
            --warn: #fbbf24;
            --bad: #f87171;
            --track: #263049;
            --shadow: 0 1px 2px rgba(0, 0, 0, .4), 0 6px 18px rgba(0, 0, 0, .25);
            color-scheme: dark;
        }

        * { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font: 14px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, Ubuntu, sans-serif;
            min-height: 100vh;
        }
        a { color: var(--accent); text-decoration: none; }
        a:hover { text-decoration: underline; }
        h1, h2, h3 { margin: 0; line-height: 1.25; }
        h2 { font-size: 16px; }
        h3 { font-size: 14px; }
        pre, code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12.5px; }
        pre { background: var(--surface-2); border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px; overflow-x: auto; margin: 6px 0; }
        .muted { color: var(--muted); }
        .small { font-size: 12px; }
        .hidden { display: none !important; }
        .nowrap { white-space: nowrap; }

        /* layout */
        .topbar {
            position: sticky; top: 0; z-index: 20;
            background: var(--surface); border-bottom: 1px solid var(--border);
        }
        .topbar-inner {
            max-width: 1280px; margin: 0 auto; padding: 0 16px;
            display: flex; align-items: center; gap: 16px; min-height: 56px;
        }
        .brand { font-weight: 700; font-size: 17px; color: var(--text); display: flex; align-items: center; gap: 8px; white-space: nowrap; }
        .brand:hover { text-decoration: none; }
        .brand-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--accent); box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 20%, transparent); }
        .badge { font-size: 11px; font-weight: 600; padding: 1px 7px; border-radius: 999px; background: var(--surface-2); color: var(--muted); border: 1px solid var(--border); }
        .nav { display: flex; gap: 4px; flex: 1; overflow-x: auto; scrollbar-width: none; }
        .nav::-webkit-scrollbar { display: none; }
        .nav a { padding: 7px 12px; border-radius: 8px; color: var(--muted); font-weight: 500; white-space: nowrap; }
        .nav a:hover { background: var(--surface-2); text-decoration: none; color: var(--text); }
        .nav a.active { background: color-mix(in srgb, var(--accent) 14%, transparent); color: var(--accent); }
        .topbar-actions { display: flex; align-items: center; gap: 8px; margin-left: auto; }
        .topbar-actions form { margin: 0; display: inline; }
        main { max-width: 1280px; margin: 0 auto; padding: 20px 16px 48px; }
        .page-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
        .page-head h1 { font-size: 20px; }
        .toolbar { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

        /* controls */
        button, .btn {
            font: inherit; cursor: pointer; border-radius: 8px; padding: 7px 14px; border: 1px solid var(--border);
            background: var(--surface); color: var(--text); display: inline-flex; align-items: center; gap: 6px; line-height: 1.2;
        }
        button:hover, .btn:hover { background: var(--surface-2); text-decoration: none; }
        button:focus-visible, a:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible { outline: 2px solid var(--accent); outline-offset: 1px; }
        button.primary, .btn.primary { background: var(--accent); border-color: var(--accent); color: var(--accent-text); font-weight: 600; }
        button.primary:hover { filter: brightness(1.08); }
        button.danger { color: var(--bad); }
        button.ghost { background: transparent; border-color: transparent; }
        button.icon { padding: 7px 10px; }
        button:disabled { opacity: .55; cursor: not-allowed; }
        input[type=text], input[type=search], input[type=email], input[type=password], input[type=number], input[type=url], select, textarea {
            font: inherit; width: 100%; padding: 8px 11px; border-radius: 8px; border: 1px solid var(--border);
            background: var(--surface); color: var(--text);
        }
        label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin: 10px 0 4px; }
        label.check { display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 500; color: var(--text); margin: 10px 0; }
        label.check input { width: auto; }
        .search { max-width: 240px; }
        .seg { display: inline-flex; border: 1px solid var(--border); border-radius: 8px; overflow: hidden; background: var(--surface); }
        .seg button { border: 0; border-radius: 0; background: transparent; padding: 6px 12px; }
        .seg button.on { background: var(--accent); color: var(--accent-text); font-weight: 600; }
        .row { display: flex; gap: 12px; flex-wrap: wrap; }
        .row > * { flex: 1 1 140px; min-width: 0; }

        /* cards */
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); box-shadow: var(--shadow); padding: 16px; }
        .card + .card { margin-top: 16px; }
        .grid > .card, .charts > .card { margin-top: 0; }
        .card-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 10px; }
        .grid { display: grid; gap: 14px; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); }
        .grid + .card, .card + .grid { margin-top: 16px; }
        .stats { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); margin-bottom: 16px; }
        .stat { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius); padding: 12px 14px; box-shadow: var(--shadow); }
        .stat .label { font-size: 12px; color: var(--muted); }
        .stat .value { font-size: 22px; font-weight: 700; margin-top: 2px; font-variant-numeric: tabular-nums; }
        .stat .value small { font-size: 12px; font-weight: 500; color: var(--muted); }

        /* server cards */
        .server { display: block; color: inherit; position: relative; transition: transform .12s ease, border-color .12s; }
        .server:hover { text-decoration: none; transform: translateY(-2px); border-color: var(--accent); }
        .server.offline { opacity: .6; }
        .server-head { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
        .flag { font-size: 20px; line-height: 1; }
        .server-name { font-weight: 600; font-size: 15px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .server-sub { font-size: 12px; color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .server-title { min-width: 0; flex: 1; }
        .dot { width: 9px; height: 9px; border-radius: 50%; background: var(--muted); flex: none; }
        .dot.on { background: var(--good); box-shadow: 0 0 0 3px color-mix(in srgb, var(--good) 22%, transparent); }
        .dot.off { background: var(--bad); }
        .meter { display: grid; grid-template-columns: 44px 1fr 48px; align-items: center; gap: 8px; margin: 7px 0; font-size: 12px; }
        .meter .name { color: var(--muted); }
        .meter .val { text-align: right; font-variant-numeric: tabular-nums; font-weight: 600; }
        .bar { height: 7px; background: var(--track); border-radius: 99px; overflow: hidden; }
        .bar > i { display: block; height: 100%; border-radius: 99px; background: var(--good); transition: width .4s ease; }
        .bar > i.warn { background: var(--warn); }
        .bar > i.bad { background: var(--bad); }
        .kv { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 12px; margin-top: 10px; padding-top: 10px; border-top: 1px dashed var(--border); font-size: 12px; }
        .kv span:nth-child(odd) { color: var(--muted); }
        .kv span:nth-child(even) { text-align: right; font-variant-numeric: tabular-nums; }

        /* tables */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 9px 10px; border-bottom: 1px solid var(--border); white-space: nowrap; }
        th { font-size: 12px; color: var(--muted); font-weight: 600; }
        tr:last-child td { border-bottom: 0; }
        tbody tr.clickable { cursor: pointer; }
        tbody tr.clickable:hover { background: var(--surface-2); }
        td .bar { min-width: 70px; }
        td.actions { text-align: right; }
        td.actions button { padding: 4px 9px; }

        /* pills */
        .pill { display: inline-block; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px; background: var(--surface-2); color: var(--muted); text-transform: uppercase; letter-spacing: .03em; }
        .pill.good { background: color-mix(in srgb, var(--good) 16%, transparent); color: var(--good); }
        .pill.bad { background: color-mix(in srgb, var(--bad) 16%, transparent); color: var(--bad); }
        .pill.warn { background: color-mix(in srgb, var(--warn) 18%, transparent); color: var(--warn); }

        /* charts */
        .charts { display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); }
        .chart { position: relative; }
        .chart h3 { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 4px; }
        .chart .legend { display: flex; gap: 12px; font-size: 12px; color: var(--muted); font-weight: 500; }
        .chart .legend i { display: inline-block; width: 9px; height: 9px; border-radius: 2px; margin-right: 5px; }
        .chart svg { display: block; width: 100%; height: 170px; overflow: visible; }
        .chart .axis { fill: var(--muted); font-size: 10px; }
        .chart .grid-line { stroke: var(--border); stroke-width: 1; }
        .chart-tip { position: absolute; pointer-events: none; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 6px 9px; font-size: 12px; box-shadow: var(--shadow); white-space: nowrap; z-index: 5; }
        .empty { text-align: center; padding: 36px 16px; color: var(--muted); }
        .empty h2 { color: var(--text); margin-bottom: 6px; }

        /* uptime ticks */
        .ticks { display: flex; gap: 2px; height: 22px; align-items: stretch; }
        .ticks i { flex: 1; min-width: 2px; border-radius: 2px; background: var(--track); }
        .ticks i.ok { background: var(--good); }
        .ticks i.fail { background: var(--bad); }
        .monitor { display: grid; gap: 10px; }
        .monitor-head { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .monitor-head .grow { flex: 1; min-width: 0; }

        /* dialog + toast */
        dialog { border: 1px solid var(--border); border-radius: var(--radius); background: var(--surface); color: var(--text); padding: 0; width: min(520px, calc(100vw - 24px)); box-shadow: 0 20px 60px rgba(0, 0, 0, .35); }
        dialog::backdrop { background: rgba(10, 14, 25, .55); }
        dialog form, dialog .dialog-body { padding: 18px; }
        dialog h2 { margin-bottom: 4px; }
        .dialog-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px; flex-wrap: wrap; }
        .toasts { position: fixed; right: 16px; bottom: 16px; display: grid; gap: 8px; z-index: 50; }
        .toast { background: var(--text); color: var(--bg); padding: 9px 14px; border-radius: 10px; box-shadow: var(--shadow); max-width: 340px; }
        .toast.error { background: var(--bad); color: #fff; }

        /* login */
        .auth-wrap { min-height: calc(100vh - 56px); display: grid; place-items: center; padding: 16px; }
        .auth-card { width: min(380px, 100%); }
        .auth-card h1 { font-size: 20px; margin-bottom: 4px; }
        .error-text { color: var(--bad); margin: 8px 0 0; }
        .banner { padding: 12px 16px; border-radius: var(--radius); font-weight: 600; margin-bottom: 16px; border: 1px solid var(--border); background: var(--surface); }
        .banner.good { border-color: color-mix(in srgb, var(--good) 45%, var(--border)); color: var(--good); }
        .banner.bad { border-color: color-mix(in srgb, var(--bad) 45%, var(--border)); color: var(--bad); }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 6px 24px; }
        .info-grid div { display: flex; justify-content: space-between; gap: 12px; padding: 6px 0; border-bottom: 1px solid var(--border); }
        .info-grid div span:first-child { color: var(--muted); }
        .info-grid div span:last-child { text-align: right; overflow-wrap: anywhere; }
        .skeleton { background: linear-gradient(90deg, var(--surface-2), var(--surface), var(--surface-2)); background-size: 200% 100%; animation: sk 1.3s infinite; border-radius: var(--radius); min-height: 150px; }
        @keyframes sk { to { background-position: -200% 0; } }
        footer { text-align: center; color: var(--muted); font-size: 12px; padding: 0 16px 24px; }

        /* responsive */
        @media (max-width: 720px) {
            .topbar-inner { flex-wrap: wrap; gap: 4px 10px; padding-top: 8px; padding-bottom: 6px; }
            .nav { order: 3; flex-basis: 100%; }
            .topbar-actions { margin-left: auto; }
            .brand .badge { display: none; }
            main { padding: 14px 12px 40px; }
            .grid { grid-template-columns: 1fr; }
            .charts { grid-template-columns: 1fr; }
            .search { max-width: none; flex: 1; }
            .stat .value { font-size: 18px; }
            th, td { padding: 8px 8px; }
        }
        @media (prefers-reduced-motion: reduce) { * { animation: none !important; transition: none !important; } }
    </style>
    <?php
}
?>
