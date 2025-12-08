<?php
/**
 * Režijska dela - Beleženje ur dela v proizvodnji
 * Lokacija: C:\BriPHP\bxroot\apps\overhead-tracker\report.php
 */
?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Režijska dela | Beleženje ur dela v proizvodnji</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <style>
        :root {
            --bg-white: #ffffff;
            --bg-light: #f8fafc;
            --bg-gray: #f1f5f9;
            --actual: #0f172a;
            --blue-600: #2563eb;
            --blue-100: #dbeafe;
            --blue-50: #eff6ff;
            --teal-600: #0d9488;
            --amber-600: #d97706;
            --violet-600: #7c3aed;
            --rose-600: #e11d48;
            --emerald-600: #059669;
            --emerald-100: #d1fae5;
            --rose-100: #ffe4e6;
            --positive: #059669;
            --negative: #dc2626;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
            --border: #e2e8f0;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--bg-light);
            color: var(--text-primary);
            font-size: 14px;
            line-height: 1.5;
        }
        .loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(255,255,255,0.97);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.3s;
        }
        .loading-overlay.hidden { opacity: 0; pointer-events: none; }
        .spinner {
            width: 44px;
            height: 44px;
            border: 4px solid var(--bg-gray);
            border-top-color: var(--blue-600);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loading-text { margin-top: 14px; font-weight: 500; color: var(--text-secondary); }
        .loading-subtext { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
        .container { max-width: 1560px; margin: 0 auto; padding: 18px 22px; }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 2px solid var(--actual);
        }
        .header h1 { font-size: 19px; font-weight: 700; }
        .header-subtitle { font-size: 12px; color: var(--text-secondary); margin-top: 2px; }
        .btn {
            padding: 9px 16px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            border: none;
            background: var(--blue-600);
            color: white;
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }
        .btn:hover { background: #1d4ed8; }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .filter-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 18px;
            padding: 12px 14px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 8px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group { display: flex; flex-direction: column; gap: 4px; }
        .filter-label { font-size: 10px; font-weight: 600; text-transform: uppercase; color: var(--text-secondary); }
        .filter-select {
            padding: 7px 28px 7px 10px;
            font-size: 13px;
            font-weight: 500;
            border: 1px solid var(--border);
            border-radius: 5px;
            background: white url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 10 10'%3E%3Cpath fill='%2364748b' d='M5 7L1 3h8z'/%3E%3C/svg%3E") no-repeat right 9px center;
            appearance: none;
            min-width: 160px;
        }
        .dn-selector-container { flex: 1; min-width: 260px; }
        .dn-selector { position: relative; }
        .dn-selector-trigger {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 6px 10px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 5px;
            cursor: pointer;
            min-height: 34px;
        }
        .dn-selector-trigger:hover { border-color: var(--blue-600); }
        .dn-selected-tags { display: flex; flex-wrap: wrap; gap: 4px; flex: 1; }
        .dn-mini-tag {
            padding: 2px 7px;
            font-size: 10px;
            font-weight: 600;
            background: var(--blue-100);
            color: var(--blue-600);
            border-radius: 3px;
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .dn-mini-tag.more { background: var(--bg-gray); color: var(--text-secondary); }
        .dn-selector-arrow { color: var(--text-muted); transition: transform 0.2s; flex-shrink: 0; }
        .dn-selector.open .dn-selector-arrow { transform: rotate(180deg); }
        .dn-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid var(--border);
            border-radius: 6px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
            z-index: 100;
            max-height: 320px;
            overflow-y: auto;
            display: none;
            margin-top: 4px;
        }
        .dn-selector.open .dn-dropdown { display: block; }
        .dn-dropdown-header {
            display: flex;
            justify-content: space-between;
            padding: 9px 11px;
            border-bottom: 1px solid var(--border);
            background: var(--bg-light);
            position: sticky;
            top: 0;
        }
        .dn-dropdown-actions { display: flex; gap: 10px; font-size: 11px; }
        .dn-dropdown-actions a { color: var(--blue-600); cursor: pointer; }
        .dn-option {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            padding: 9px 11px;
            cursor: pointer;
            border-bottom: 1px solid var(--bg-gray);
        }
        .dn-option:hover { background: var(--blue-50); }
        .dn-checkbox {
            width: 15px;
            height: 15px;
            border: 2px solid var(--border);
            border-radius: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .dn-option.selected .dn-checkbox { background: var(--blue-600); border-color: var(--blue-600); }
        .dn-checkbox svg { opacity: 0; }
        .dn-option.selected .dn-checkbox svg { opacity: 1; }
        .dn-option-id { font-weight: 700; font-size: 10px; color: var(--blue-600); }
        .dn-option-name { font-size: 11px; color: var(--text-primary); }
        .main-layout { display: grid; grid-template-columns: 1fr 260px; gap: 18px; }
        .card { background: white; border: 1px solid var(--border); border-radius: 8px; overflow: hidden; }
        .card-header {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-light);
        }
        .card-title { font-size: 12px; font-weight: 700; }
        .card-body { padding: 12px; }
        .kpi-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 18px; }
        .kpi-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 7px;
            padding: 12px 14px;
            border-left: 4px solid var(--blue-600);
        }
        .kpi-card:nth-child(2) { border-left-color: var(--teal-600); }
        .kpi-card:nth-child(3) { border-left-color: var(--amber-600); }
        .kpi-card:nth-child(4) { border-left-color: var(--violet-600); }
        .kpi-value { font-family: 'JetBrains Mono', monospace; font-size: 24px; font-weight: 600; }
        .kpi-label { font-size: 10px; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; margin-top: 3px; }
        .kpi-change { font-size: 10px; font-weight: 600; margin-top: 5px; padding: 2px 5px; border-radius: 3px; display: inline-block; }
        .kpi-change.positive { color: var(--positive); background: var(--emerald-100); }
        .kpi-change.negative { color: var(--negative); background: var(--rose-100); }
        .tabs-container { background: white; border: 1px solid var(--border); border-radius: 7px 7px 0 0; border-bottom: none; }
        .tabs { display: flex; border-bottom: 2px solid var(--border); }
        .tab {
            padding: 10px 18px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            background: none;
            border: none;
            cursor: pointer;
            position: relative;
        }
        .tab:hover { color: var(--text-primary); }
        .tab.active { color: var(--blue-600); }
        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--blue-600);
        }
        .section { display: none; }
        .section.active { display: block; }
        .evidence-wrapper { background: white; border: 1px solid var(--border); border-top: none; border-radius: 0 0 7px 7px; }
        .evidence-scroll { max-height: calc(100vh - 330px); overflow-y: auto; }
        .group-header-l1 {
            background: var(--actual);
            color: white;
            padding: 9px 14px;
            font-weight: 600;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 9px;
            position: sticky;
            top: 0;
            z-index: 20;
        }
        .group-header-l1 .dot { width: 9px; height: 9px; border-radius: 2px; background: white; flex-shrink: 0; }
        .group-header-l1 .group-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .group-stats { display: flex; gap: 14px; font-size: 10px; font-weight: 500; opacity: 0.85; flex-shrink: 0; }
        .group-stats span { font-family: 'JetBrains Mono', monospace; }
        .group-header-l2 {
            background: var(--bg-gray);
            padding: 7px 14px 7px 26px;
            font-weight: 600;
            font-size: 11px;
            display: flex;
            align-items: center;
            gap: 7px;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 35px;
            z-index: 15;
        }
        .group-header-l2 .dot { width: 7px; height: 7px; border-radius: 2px; flex-shrink: 0; }
        .table-header-row {
            display: grid;
            grid-template-columns: 28px 1fr 90px 60px 60px 55px;
            padding: 8px 14px;
            background: white;
            border-bottom: 2px solid var(--actual);
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-secondary);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .has-group-l1 .table-header-row { top: 35px; }
        .has-group-l2 .table-header-row { top: 30px; }
        .has-both-groups .table-header-row { top: 65px; }
        .table-header-row span:nth-child(n+3) { text-align: right; }
        .employee-row { border-bottom: 1px solid var(--border); }
        .employee-main {
            display: grid;
            grid-template-columns: 28px 1fr 90px 60px 60px 55px;
            align-items: center;
            padding: 8px 14px;
            cursor: pointer;
        }
        .employee-main:hover { background: var(--bg-light); }
        .expand-icon { width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; color: var(--text-muted); transition: transform 0.2s; }
        .employee-row.expanded .expand-icon { transform: rotate(90deg); color: var(--blue-600); }
        .employee-info { display: flex; align-items: center; gap: 8px; min-width: 0; }
        .employee-avatar {
            width: 28px;
            height: 28px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 700;
            color: white;
            flex-shrink: 0;
        }
        .employee-details { min-width: 0; flex: 1; }
        .employee-name { font-weight: 600; font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .employee-meta { font-size: 10px; color: var(--text-secondary); }
        .cell-value { font-family: 'JetBrains Mono', monospace; font-size: 11px; text-align: right; }
        .avg-time { display: flex; align-items: center; justify-content: flex-end; gap: 5px; }
        .time-dot { width: 5px; height: 5px; border-radius: 50%; flex-shrink: 0; }
        .loc-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: 600;
            max-width: 100%;
            overflow: hidden;
        }
        .loc-badge .dot { width: 5px; height: 5px; border-radius: 50%; flex-shrink: 0; }
        .loc-badge .loc-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .detail-panel { display: none; background: var(--bg-light); border-top: 1px solid var(--border); }
        .employee-row.expanded .detail-panel { display: block; }
        .detail-header {
            display: grid;
            grid-template-columns: 75px 1fr 100px 55px 55px 50px;
            padding: 7px 14px 7px 42px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-secondary);
            background: var(--bg-gray);
            border-bottom: 1px solid var(--border);
        }
        .detail-header span:nth-child(n+4) { text-align: right; }
        .detail-item {
            display: grid;
            grid-template-columns: 75px 1fr 100px 55px 55px 50px;
            padding: 6px 14px 6px 42px;
            font-size: 11px;
            border-bottom: 1px solid var(--bg-gray);
            align-items: center;
            background: white;
        }
        .detail-item:hover { background: var(--blue-50); }
        .detail-date { font-family: 'JetBrains Mono', monospace; font-size: 11px; }
        .detail-dn { display: flex; align-items: center; gap: 5px; min-width: 0; }
        .detail-dn-id { font-weight: 600; font-size: 10px; padding: 1px 5px; border-radius: 2px; white-space: nowrap; flex-shrink: 0; }
        .detail-wc { font-size: 10px; color: var(--text-secondary); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .detail-time { font-family: 'JetBrains Mono', monospace; font-size: 10px; text-align: right; }
        .detail-duration { font-family: 'JetBrains Mono', monospace; font-size: 11px; font-weight: 600; color: var(--blue-600); text-align: right; }
        .summary-section { margin-bottom: 14px; }
        .summary-title { font-size: 9px; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); margin-bottom: 6px; padding-bottom: 5px; border-bottom: 2px solid var(--border); }
        .summary-item { display: flex; justify-content: space-between; align-items: flex-start; padding: 4px 0; border-bottom: 1px solid var(--bg-gray); font-size: 10px; gap: 6px; }
        .summary-label { display: flex; align-items: flex-start; gap: 5px; flex: 1; min-width: 0; }
        .summary-label-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .summary-dot { width: 6px; height: 6px; border-radius: 2px; flex-shrink: 0; margin-top: 3px; }
        .summary-value { font-family: 'JetBrains Mono', monospace; font-size: 10px; font-weight: 600; flex-shrink: 0; }
        .summary-bar { height: 3px; background: var(--bg-gray); border-radius: 2px; margin-top: 2px; overflow: hidden; }
        .summary-bar-fill { height: 100%; border-radius: 2px; }
        .peak-chart { display: flex; align-items: flex-end; height: 50px; gap: 1px; padding: 3px 0; border-bottom: 2px solid var(--border); }
        .peak-bar { flex: 1; background: var(--blue-100); border-radius: 1px 1px 0 0; min-height: 2px; }
        .peak-bar.highlight { background: var(--blue-600); }
        .peak-labels { display: flex; gap: 1px; margin-top: 2px; }
        .peak-label { flex: 1; text-align: center; font-size: 7px; color: var(--text-muted); }
        .heatmap-container { overflow-x: auto; padding: 6px; }
        .heatmap { display: grid; grid-template-columns: 50px repeat(24, 1fr); gap: 1px; min-width: 500px; }
        .heatmap-header { font-size: 7px; font-weight: 600; color: var(--text-secondary); text-align: center; padding: 2px; }
        .heatmap-row-label { font-size: 9px; font-weight: 600; display: flex; align-items: center; }
        .heatmap-cell { height: 16px; border-radius: 2px; background: var(--bg-gray); position: relative; cursor: pointer; }
        .heatmap-cell:hover { transform: scale(1.2); z-index: 1; }
        .heatmap-cell[data-intensity="1"] { background: var(--blue-100); }
        .heatmap-cell[data-intensity="2"] { background: #93c5fd; }
        .heatmap-cell[data-intensity="3"] { background: #60a5fa; }
        .heatmap-cell[data-intensity="4"] { background: #3b82f6; }
        .heatmap-cell[data-intensity="5"] { background: var(--blue-600); }
        .heatmap-tooltip { position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); background: var(--actual); color: white; padding: 3px 6px; border-radius: 3px; font-size: 9px; white-space: nowrap; opacity: 0; pointer-events: none; z-index: 10; }
        .heatmap-cell:hover .heatmap-tooltip { opacity: 1; }
        .timeline-hours { display: flex; padding-left: 100px; margin-bottom: 3px; }
        .timeline-hour-label { flex: 1; font-size: 8px; color: var(--text-muted); text-align: center; }
        .timeline-row { display: flex; align-items: center; margin-bottom: 4px; }
        .timeline-label { width: 100px; font-size: 10px; font-weight: 600; padding-right: 6px; display: flex; align-items: center; gap: 5px; overflow: hidden; }
        .timeline-label .avatar { width: 18px; height: 18px; border-radius: 3px; font-size: 7px; flex-shrink: 0; }
        .timeline-bar-container { flex: 1; height: 16px; background: var(--bg-gray); border-radius: 2px; position: relative; }
        .timeline-hour-mark { position: absolute; top: 0; bottom: 0; width: 1px; background: var(--border); }
        .timeline-entry { position: absolute; top: 2px; bottom: 2px; border-radius: 2px; min-width: 3px; }
        .legend { display: flex; gap: 10px; padding: 6px 12px; background: var(--bg-light); border-top: 1px solid var(--border); font-size: 9px; flex-wrap: wrap; }
        .legend-item { display: flex; align-items: center; gap: 3px; color: var(--text-secondary); }
        .legend-box { width: 8px; height: 5px; border-radius: 1px; }
        .toast { position: fixed; bottom: 20px; right: 20px; padding: 10px 18px; background: var(--actual); color: white; font-size: 12px; font-weight: 600; border-radius: 5px; z-index: 1001; transform: translateY(80px); opacity: 0; transition: all 0.3s; }
        .toast.active { transform: translateY(0); opacity: 1; }
        .no-data { padding: 30px; text-align: center; color: var(--text-muted); font-size: 13px; }
        @media (max-width: 1200px) { .main-layout { grid-template-columns: 1fr; } .kpi-row { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body>
    <div class="loading-overlay" id="loading-overlay">
        <div class="spinner"></div>
        <div class="loading-text">Nalagam podatke...</div>
        <div class="loading-subtext">Povezovanje z bazo podatkov</div>
    </div>
    <div class="container">
        <header class="header">
            <div>
                <h1>Režijska dela</h1>
                <div class="header-subtitle">Beleženje ur dela v proizvodnji</div>
            </div>
            <button class="btn" id="pdf-btn" onclick="generatePDF()" disabled>
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                Izvozi PDF
            </button>
        </header>
        <div class="filter-bar">
            <div class="filter-group">
                <span class="filter-label">Obdobje</span>
                <select class="filter-select" id="period-filter" onchange="loadData()">
                    <option value="all" selected>Vsi podatki</option>
                    <option value="ytd">YTD</option>
                    <option value="current-month">Trenutni mesec</option>
                    <option value="prev-month">Pretekli mesec</option>
                </select>
            </div>
            <div class="filter-group">
                <span class="filter-label">Grupiraj (1. nivo)</span>
                <select class="filter-select" id="group-filter-1" onchange="applyFilters()">
                    <option value="none">Brez grupiranja</option>
                    <option value="location" selected>Lokacija</option>
                    <option value="cost-center">Stroškovno mesto</option>
                    <option value="employment-type">Tip zaposlitve</option>
                    <option value="dn">Delovni nalog</option>
                </select>
            </div>
            <div class="filter-group">
                <span class="filter-label">Grupiraj (2. nivo)</span>
                <select class="filter-select" id="group-filter-2" onchange="applyFilters()">
                    <option value="none" selected>Brez</option>
                    <option value="location">Lokacija</option>
                    <option value="cost-center">Stroškovno mesto</option>
                    <option value="employment-type">Tip zaposlitve</option>
                    <option value="dn">Delovni nalog</option>
                </select>
            </div>
            <div class="filter-group dn-selector-container">
                <span class="filter-label">Delovni nalogi</span>
                <div class="dn-selector" id="dn-selector">
                    <div class="dn-selector-trigger" onclick="toggleDNDropdown()">
                        <div class="dn-selected-tags" id="dn-selected-tags"><span style="color:var(--text-muted);font-size:11px">Nalagam...</span></div>
                        <svg class="dn-selector-arrow" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                    </div>
                    <div class="dn-dropdown" id="dn-dropdown">
                        <div class="dn-dropdown-header">
                            <span style="font-size:10px;font-weight:600;color:var(--text-secondary)">Izberi DN</span>
                            <div class="dn-dropdown-actions"><a onclick="selectAllDN()">Vse</a><a onclick="deselectAllDN()">Počisti</a></div>
                        </div>
                        <div id="dn-options"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="kpi-row">
            <div class="kpi-card"><div class="kpi-value" id="kpi-entries">--</div><div class="kpi-label">Evidenc</div><div class="kpi-change" id="kpi-entries-change">--</div></div>
            <div class="kpi-card"><div class="kpi-value" id="kpi-hours">--</div><div class="kpi-label">Skupaj ur</div><div class="kpi-change" id="kpi-hours-change">--</div></div>
            <div class="kpi-card"><div class="kpi-value" id="kpi-avg">--</div><div class="kpi-label">Povprečje</div><div class="kpi-change" id="kpi-avg-change">--</div></div>
            <div class="kpi-card"><div class="kpi-value" id="kpi-employees">--</div><div class="kpi-label">Zaposlenih</div><div class="kpi-change" id="kpi-employees-change">--</div></div>
        </div>
        <div class="main-layout">
            <div class="main-content">
                <div class="tabs-container">
                    <div class="tabs">
                        <button class="tab active" onclick="switchTab('evidence')">Pregled evidenc</button>
                        <button class="tab" onclick="switchTab('heatmap')">Urna analiza</button>
                        <button class="tab" onclick="switchTab('daily')">Dnevni pregled</button>
                    </div>
                </div>
                <div id="section-evidence" class="section active"><div class="evidence-wrapper"><div class="evidence-scroll" id="entries-container"></div></div></div>
                <div id="section-heatmap" class="section"><div class="card" style="border-radius:0 0 7px 7px;border-top:none;"><div class="card-body" style="padding:6px;"><div class="heatmap-container"><div class="heatmap" id="heatmap"></div></div></div></div></div>
                <div id="section-daily" class="section">
                    <div class="card" style="border-radius:0 0 7px 7px;border-top:none;">
                        <div class="card-header"><span class="card-title">Urni pregled</span><select class="filter-select" id="daily-date" onchange="renderDailyTimeline()" style="min-width:120px;"></select></div>
                        <div class="card-body"><div class="timeline-hours" id="timeline-hours"></div><div id="daily-timeline"></div></div>
                        <div class="legend" id="location-legend"></div>
                    </div>
                </div>
            </div>
            <aside>
                <div class="card">
                    <div class="card-header"><span class="card-title">Povzetek</span></div>
                    <div class="card-body">
                        <div class="summary-section"><div class="summary-title">Vrhunec prijav</div><div class="peak-chart" id="peak-chart"></div><div class="peak-labels" id="peak-labels"></div></div>
                        <div class="summary-section"><div class="summary-title">Po lokacijah</div><div id="summary-location"></div></div>
                        <div class="summary-section"><div class="summary-title">Po delovnih nalogih</div><div id="summary-dn"></div></div>
                        <div class="summary-section"><div class="summary-title">Top zaposleni</div><div id="summary-employees"></div></div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
    <div class="toast" id="toast"></div>
    <script>
        let rawData=[],prevPeriodData=[],entries=[],delovniNalogi=[],selectedDN=new Set(),lokacije=[];
        const colors=['#2563eb','#0d9488','#7c3aed','#d97706','#e11d48','#059669','#475569','#0891b2','#c026d3','#ea580c','#4f46e5','#db2777'];
        const getColor=i=>colors[i%colors.length];
        const getInitials=n=>{if(!n)return'??';const p=n.trim().split(/\s+/);return p.length>=2?(p[0][0]+p[p.length-1][0]).toUpperCase():n.substring(0,2).toUpperCase();};
        const showLoading=()=>{document.getElementById('loading-overlay').classList.remove('hidden');document.getElementById('pdf-btn').disabled=true;};
        const hideLoading=()=>{document.getElementById('loading-overlay').classList.add('hidden');document.getElementById('pdf-btn').disabled=false;};
        async function loadData(){
            showLoading();
            const period=document.getElementById('period-filter').value;
            try{
                const[cR,pR]=await Promise.all([fetch(`query.php?period=${period}&type=current`),fetch(`query.php?period=${period}&type=previous`)]);
                rawData=await cR.json();prevPeriodData=await pR.json();
                if(rawData.error){showToast('Napaka: '+rawData.error);rawData=[];}
                processData();applyFilters();
            }catch(e){console.error(e);showToast('Napaka pri nalaganju');rawData=[];processData();applyFilters();}
            hideLoading();
        }
        function processData(){
            const dnMap={};
            rawData.forEach(r=>{const id=r['Order ID']||'',nm=r['Order Name']||'';if(id&&!dnMap[id])dnMap[id]={id,name:nm,fullName:`${id} - ${nm}`,color:getColor(Object.keys(dnMap).length)};});
            delovniNalogi=Object.values(dnMap);selectedDN=new Set(delovniNalogi.map(d=>d.id));
            const locMap={};
            rawData.forEach(r=>{const l=r['Location']||'Neznano';if(!locMap[l])locMap[l]={id:l.toLowerCase().replace(/\s+/g,'-'),name:l,color:getColor(Object.keys(locMap).length)};});
            lokacije=Object.values(locMap);
            entries=rawData.map((r,i)=>{
                const orderId=r['Order ID']||'';
                const dn=delovniNalogi.find(d=>d.id===orderId)||{id:orderId,name:'',color:'#475569'};
                const locName=r['Location']||'Neznano';
                const loc=lokacije.find(l=>l.name===locName)||{id:'neznano',name:'Neznano',color:'#94a3b8'};
                // Nimamo podatkov o uri začetka/konca - samo skupne ure
                return{id:i+1,employee:{id:r['Person ID']||i,name:r['Person Name']||'Neznano',location:loc.id,costCenter:r['Person Cost Center ID Name']||'Neznano',employmentType:r['Person Group Name']||'Neznano',initials:getInitials(r['Person Name'])},dn,workCenter:r['Work Center ID Name']||'',operationText:r['Operation Text']||'',checkin:null,checkout:null,datePosting:r['Date Posting']?new Date(r['Date Posting']):new Date(),duration:parseFloat(r['M Hours'])||0};
            });
            renderDNOptions();updateDNSelectedTags();renderLocationLegend();
        }
        function renderDNOptions(){document.getElementById('dn-options').innerHTML=delovniNalogi.map(d=>`<div class="dn-option ${selectedDN.has(d.id)?'selected':''}" onclick="toggleDN('${d.id}')"><div class="dn-checkbox"><svg width="9" height="9" fill="none" stroke="white" stroke-width="3" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></div><div><div class="dn-option-id" style="color:${d.color}">${d.id}</div><div class="dn-option-name">${d.name}</div></div></div>`).join('');}
        function updateDNSelectedTags(){const c=document.getElementById('dn-selected-tags'),s=Array.from(selectedDN);if(!s.length)c.innerHTML='<span style="color:var(--text-muted);font-size:11px">Izberi...</span>';else if(s.length===delovniNalogi.length)c.innerHTML='<span class="dn-mini-tag">Vsi DN</span>';else if(s.length<=2)c.innerHTML=s.map(id=>`<span class="dn-mini-tag">${id}</span>`).join('');else c.innerHTML=s.slice(0,2).map(id=>`<span class="dn-mini-tag">${id}</span>`).join('')+`<span class="dn-mini-tag more">+${s.length-2}</span>`;}
        function toggleDNDropdown(){document.getElementById('dn-selector').classList.toggle('open');}
        function toggleDN(id){if(selectedDN.has(id)){if(selectedDN.size>1)selectedDN.delete(id);}else selectedDN.add(id);renderDNOptions();updateDNSelectedTags();applyFilters();}
        function selectAllDN(){delovniNalogi.forEach(d=>selectedDN.add(d.id));renderDNOptions();updateDNSelectedTags();applyFilters();}
        function deselectAllDN(){selectedDN.clear();if(delovniNalogi.length)selectedDN.add(delovniNalogi[0].id);renderDNOptions();updateDNSelectedTags();applyFilters();}
        function renderLocationLegend(){document.getElementById('location-legend').innerHTML='<span style="font-weight:600">Lokacije:</span>'+lokacije.map(l=>`<div class="legend-item"><div class="legend-box" style="background:${l.color}"></div>${l.name}</div>`).join('');}
        function getFilteredEntries(){return entries.filter(e=>selectedDN.has(e.dn.id));}
        function applyFilters(){const f=getFilteredEntries();renderKPIs(f);renderEntriesTable(f);renderHeatmap(f);renderSummary(f);populateDailyDates(f);renderDailyTimeline();}
        function renderKPIs(f){const tot=f.length,hrs=f.reduce((s,e)=>s+(e.duration||0),0),avg=tot?hrs/tot:0,emp=new Set(f.map(e=>e.employee.id)).size;const pTot=prevPeriodData.length||0,pHrs=Array.isArray(prevPeriodData)?prevPeriodData.reduce((s,e)=>s+(parseFloat(e['M Hours'])||0),0):0,pAvg=pTot?pHrs/pTot:0,pEmp=Array.isArray(prevPeriodData)?new Set(prevPeriodData.map(e=>e['Person ID'])).size:0;document.getElementById('kpi-entries').textContent=tot;document.getElementById('kpi-hours').textContent=hrs.toFixed(1);document.getElementById('kpi-avg').textContent=formatDuration(avg);document.getElementById('kpi-employees').textContent=emp;renderChange('kpi-entries-change',tot,pTot);renderChange('kpi-hours-change',hrs,pHrs);renderChange('kpi-avg-change',avg,pAvg,false);renderChange('kpi-employees-change',emp,pEmp);}
        function renderChange(id,c,p,hb=true){const el=document.getElementById(id);if(!p){el.textContent='--';el.className='kpi-change';return;}const pct=((c-p)/p*100).toFixed(0);el.textContent=`${pct>=0?'+':''}${pct}%`;el.className=`kpi-change ${(hb?pct>=0:pct<=0)?'positive':'negative'}`;}
        function getGroupInfo(t,id){if(t==='location')return lokacije.find(l=>l.id===id)||{name:id,color:'#94a3b8'};if(t==='cost-center')return{id,name:id,color:getColor(Math.abs(hashCode(id))%colors.length)};if(t==='employment-type')return{id,name:id,color:getColor(Math.abs(hashCode(id))%colors.length)};if(t==='dn')return delovniNalogi.find(d=>d.id===id)||{id,name:id,color:'#475569'};return null;}
        function getGroupKey(e,t){if(t==='location')return e.employee.location;if(t==='cost-center')return e.employee.costCenter;if(t==='employment-type')return e.employee.employmentType;if(t==='dn')return e.dn.id;return'all';}
        function hashCode(s){let h=0;for(let i=0;i<s.length;i++)h=((h<<5)-h)+s.charCodeAt(i)|0;return h;}
        function renderEntriesTable(filtered){const g1=document.getElementById('group-filter-1').value,g2=document.getElementById('group-filter-2').value,container=document.getElementById('entries-container');if(!filtered.length){container.innerHTML='<div class="no-data">Ni podatkov za prikaz</div>';return;}const byEmp={};filtered.forEach(e=>{const k=e.employee.id;if(!byEmp[k])byEmp[k]={employee:e.employee,entries:[],totalHours:0,count:0,hourSum:0};byEmp[k].entries.push(e);byEmp[k].totalHours+=e.duration||0;byEmp[k].count++;if(e.checkin)byEmp[k].hourSum+=e.checkin.getHours()+e.checkin.getMinutes()/60;});Object.values(byEmp).forEach(d=>{d.avgHour=d.count?d.hourSum/d.count:0;});const groups1={};Object.values(byEmp).forEach(empData=>{const k1=g1!=='none'?getGroupKey(empData.entries[0],g1):'all';if(!groups1[k1])groups1[k1]={};const k2=g2!=='none'?getGroupKey(empData.entries[0],g2):'all';if(!groups1[k1][k2])groups1[k1][k2]=[];groups1[k1][k2].push(empData);});const hasG1=g1!=='none',hasG2=g2!=='none';let wc='';if(hasG1&&hasG2)wc='has-both-groups';else if(hasG1)wc='has-group-l1';else if(hasG2)wc='has-group-l2';let html=`<div class="${wc}">`;Object.entries(groups1).sort((a,b)=>{const aH=Object.values(a[1]).flat().reduce((s,d)=>s+d.totalHours,0),bH=Object.values(b[1]).flat().reduce((s,d)=>s+d.totalHours,0);return bH-aH;}).forEach(([k1,subGroups])=>{const info1=getGroupInfo(g1,k1),g1TotH=Object.values(subGroups).flat().reduce((s,d)=>s+d.totalHours,0),g1TotE=Object.values(subGroups).flat().reduce((s,d)=>s+d.count,0);if(hasG1){const nm=g1==='dn'?(info1?.name||k1):(info1?.name||k1);html+=`<div class="group-header-l1"><div class="dot" style="background:${info1?.color||'white'}"></div><span class="group-name">${nm}</span><div class="group-stats"><div><span>${g1TotE}</span> evidenc</div><div><span>${g1TotH.toFixed(1)}</span> ur</div></div></div>`;}Object.entries(subGroups).sort((a,b)=>b[1].reduce((s,d)=>s+d.totalHours,0)-a[1].reduce((s,d)=>s+d.totalHours,0)).forEach(([k2,empData])=>{const info2=getGroupInfo(g2,k2),g2TotH=empData.reduce((s,d)=>s+d.totalHours,0),g2TotE=empData.reduce((s,d)=>s+d.count,0);if(hasG2)html+=`<div class="group-header-l2"><div class="dot" style="background:${info2?.color||'var(--actual)'}"></div><span>${info2?.name||k2}</span><div class="group-stats" style="font-size:9px"><div><span>${g2TotE}</span> evidenc</div><div><span>${g2TotH.toFixed(1)}</span> ur</div></div></div>`;html+=`<div class="table-header-row"><span></span><span>Zaposleni</span><span>Lokacija</span><span>Evidenc</span><span>Skupaj</span><span>Povp.</span></div>`;empData.sort((a,b)=>b.totalHours-a.totalHours).forEach(data=>{const loc=lokacije.find(l=>l.id===data.employee.location)||{name:data.employee.location,color:'#94a3b8'},rowId=`row-${data.employee.id}-${k1}-${k2}`.replace(/[^a-zA-Z0-9-]/g,'_');html+=`<div class="employee-row" id="${rowId}"><div class="employee-main" onclick="toggleRow('${rowId}')"><div class="expand-icon"><svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg></div><div class="employee-info"><div class="employee-avatar" style="background:${loc.color}">${data.employee.initials}</div><div class="employee-details"><div class="employee-name">${data.employee.name}</div><div class="employee-meta">${data.employee.employmentType}</div></div></div><div><div class="loc-badge" style="background:${loc.color}12;color:${loc.color}"><span class="dot" style="background:${loc.color}"></span><span class="loc-name">${loc.name}</span></div></div><div class="cell-value">${data.count}</div><div class="cell-value">${data.totalHours.toFixed(1)}h</div><div class="avg-time"><span class="time-dot" style="background:${getTimeColor(data.avgHour)}"></span><span class="cell-value">${formatHour(data.avgHour)}</span></div></div><div class="detail-panel"><div class="detail-list"><div class="detail-header"><span>Datum</span><span>Delovni nalog</span><span>Operacija</span><span>Str. mesto</span><span></span><span>Ure</span></div>${data.entries.sort((a,b)=>b.datePosting-a.datePosting).map(e=>`<div class="detail-item"><div class="detail-date">${e.datePosting.toLocaleDateString('sl-SI',{day:'2-digit',month:'2-digit',year:'numeric'})}</div><div class="detail-dn"><span class="detail-dn-id" style="background:${e.dn.color}18;color:${e.dn.color}">${e.dn.name||e.dn.id}</span></div><div class="detail-wc" title="${e.operationText||e.workCenter}">${e.operationText||e.workCenter}</div><div class="detail-time">${e.workCenter}</div><div class="detail-time"></div><div class="detail-duration">${e.duration.toFixed(2)}h</div></div>`).join('')}</div></div></div>`;});});});html+='</div>';container.innerHTML=html;}
        function toggleRow(id){const el=document.getElementById(id);if(el)el.classList.toggle('expanded');}
        function getTimeColor(h){if(h<9)return'#0d9488';if(h<12)return'#2563eb';if(h<15)return'#d97706';return'#e11d48';}
        function formatHour(h){if(!h||isNaN(h))return'--:--';const hr=Math.floor(h),mn=Math.round((h-hr)*60);return`${hr.toString().padStart(2,'0')}:${mn.toString().padStart(2,'0')}`;}
        function formatTimeFromDate(d){if(!d)return'--:--';return d.toLocaleTimeString('sl-SI',{hour:'2-digit',minute:'2-digit'});}
        function renderHeatmap(f){const days=['Pon','Tor','Sre','Čet','Pet','Sob','Ned'],heatData={};days.forEach((_,i)=>{heatData[i]={};for(let h=0;h<24;h++)heatData[i][h]=0;});f.forEach(e=>{if(e.checkin){let di=e.datePosting.getDay()-1;if(di<0)di=6;heatData[di][e.checkin.getHours()]++;}});let max=1;Object.values(heatData).forEach(hrs=>Object.values(hrs).forEach(c=>{if(c>max)max=c;}));let html='<div class="heatmap-header"></div>';for(let h=0;h<24;h++)html+=`<div class="heatmap-header">${h.toString().padStart(2,'0')}</div>`;days.forEach((day,di)=>{html+=`<div class="heatmap-row-label">${day}</div>`;for(let h=0;h<24;h++){const cnt=heatData[di][h],int=cnt===0?0:Math.ceil((cnt/max)*5);html+=`<div class="heatmap-cell" data-intensity="${int}"><div class="heatmap-tooltip">${days[di]} ${h}:00 - ${cnt}</div></div>`;}});document.getElementById('heatmap').innerHTML=html;}
        function populateDailyDates(f){const dates=[...new Set(f.map(e=>e.datePosting.toDateString()))].sort((a,b)=>new Date(b)-new Date(a));document.getElementById('daily-date').innerHTML=dates.slice(0,30).map(d=>{const dt=new Date(d);return`<option value="${d}">${dt.toLocaleDateString('sl-SI',{weekday:'short',day:'numeric',month:'short'})}</option>`;}).join('')||'<option value="">Ni podatkov</option>';}
        function renderDailyTimeline(){const selDate=document.getElementById('daily-date').value;if(!selDate){document.getElementById('daily-timeline').innerHTML='<div class="no-data">Ni podatkov</div>';return;}const f=getFilteredEntries().filter(e=>e.datePosting.toDateString()===selDate&&e.checkin);document.getElementById('timeline-hours').innerHTML=Array.from({length:9},(_,i)=>`<span class="timeline-hour-label">${6+i*2}:00</span>`).join('');const byEmp={};f.forEach(e=>{if(!byEmp[e.employee.id])byEmp[e.employee.id]={employee:e.employee,entries:[]};byEmp[e.employee.id].entries.push(e);});document.getElementById('daily-timeline').innerHTML=Object.values(byEmp).map(data=>{const loc=lokacije.find(l=>l.id===data.employee.location)||{color:'#94a3b8'};let marks='';for(let h=6;h<=22;h++)marks+=`<div class="timeline-hour-mark" style="left:${((h-6)/16)*100}%"></div>`;const ents=data.entries.filter(e=>e.checkin).map(e=>{const sH=e.checkin.getHours()+e.checkin.getMinutes()/60,dur=e.duration||1,eH=sH+dur,sP=Math.max(0,((sH-6)/16)*100),wP=Math.min(100-sP,((eH-sH)/16)*100);return`<div class="timeline-entry" style="left:${sP}%;width:${Math.max(wP,1)}%;background:${loc.color}" title="${e.dn.name}: ${formatTimeFromDate(e.checkin)} (${e.duration.toFixed(1)}h)"></div>`;}).join('');return`<div class="timeline-row"><div class="timeline-label"><div class="avatar employee-avatar" style="background:${loc.color}">${data.employee.initials}</div><span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${data.employee.name}</span></div><div class="timeline-bar-container">${marks}${ents}</div></div>`;}).join('')||'<div class="no-data">Ni podatkov za ta dan</div>';}
        function renderSummary(f){const hourCounts={};for(let h=6;h<=20;h++)hourCounts[h]=0;f.forEach(e=>{if(e.checkin){const h=e.checkin.getHours();if(hourCounts[h]!==undefined)hourCounts[h]++;}});const maxH=Math.max(...Object.values(hourCounts),1),peakH=Object.entries(hourCounts).sort((a,b)=>b[1]-a[1])[0]?.[0];document.getElementById('peak-chart').innerHTML=Object.entries(hourCounts).map(([h,c])=>`<div class="peak-bar ${h==peakH?'highlight':''}" style="height:${Math.max((c/maxH)*100,4)}%"></div>`).join('');document.getElementById('peak-labels').innerHTML=Object.keys(hourCounts).filter((_,i)=>i%2===0).map(h=>`<span class="peak-label" style="flex:2">${h}</span>`).join('');const byLoc={};f.forEach(e=>{const loc=lokacije.find(l=>l.id===e.employee.location);const n=loc?.name||e.employee.location||'Neznano';byLoc[n]=(byLoc[n]||0)+(e.duration||0);});const maxLH=Math.max(...Object.values(byLoc),1);document.getElementById('summary-location').innerHTML=Object.entries(byLoc).sort((a,b)=>b[1]-a[1]).slice(0,5).map(([nm,hrs])=>{const loc=lokacije.find(l=>l.name===nm)||{color:'#94a3b8'};return`<div class="summary-item"><span class="summary-label"><span class="summary-dot" style="background:${loc.color}"></span><span class="summary-label-text">${nm}</span></span><span class="summary-value">${hrs.toFixed(0)}h</span></div><div class="summary-bar"><div class="summary-bar-fill" style="width:${(hrs/maxLH)*100}%;background:${loc.color}"></div></div>`;}).join('')||'<div class="no-data">Ni podatkov</div>';const byDN={};f.forEach(e=>{const nm=e.dn.name||e.dn.id;byDN[nm]=(byDN[nm]||0)+(e.duration||0);});document.getElementById('summary-dn').innerHTML=Object.entries(byDN).sort((a,b)=>b[1]-a[1]).slice(0,5).map(([nm,hrs])=>{const dn=delovniNalogi.find(d=>d.name===nm)||{color:'#475569'};return`<div class="summary-item"><span class="summary-label"><span class="summary-dot" style="background:${dn.color}"></span><span class="summary-label-text" title="${nm}">${nm}</span></span><span class="summary-value">${hrs.toFixed(0)}h</span></div>`;}).join('')||'<div class="no-data">Ni podatkov</div>';const byEmp={};f.forEach(e=>{byEmp[e.employee.id]=byEmp[e.employee.id]||{emp:e.employee,hours:0};byEmp[e.employee.id].hours+=e.duration||0;});document.getElementById('summary-employees').innerHTML=Object.values(byEmp).sort((a,b)=>b.hours-a.hours).slice(0,5).map(({emp,hours})=>{const loc=lokacije.find(l=>l.id===emp.location)||{color:'#94a3b8'};return`<div class="summary-item"><span class="summary-label"><span class="summary-dot" style="background:${loc.color}"></span><span class="summary-label-text">${emp.name}</span></span><span class="summary-value">${hours.toFixed(0)}h</span></div>`;}).join('')||'<div class="no-data">Ni podatkov</div>';}
        function switchTab(tab){document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));event.target.classList.add('active');document.querySelectorAll('.section').forEach(s=>s.classList.remove('active'));document.getElementById(`section-${tab}`).classList.add('active');if(tab==='daily')renderDailyTimeline();}
        function generatePDF(){showToast('Pripravljam PDF...');const{jsPDF}=window.jspdf;const doc=new jsPDF({orientation:'portrait',unit:'mm',format:'a4'});const pW=doc.internal.pageSize.getWidth(),pH=doc.internal.pageSize.getHeight(),m=15;let y=m;const filtered=getFilteredEntries(),period=document.getElementById('period-filter').value,periodLabels={'current-month':'Trenutni mesec','prev-month':'Pretekli mesec','ytd':'YTD'};const tot=filtered.length,hrs=filtered.reduce((s,e)=>s+(e.duration||0),0),emp=new Set(filtered.map(e=>e.employee.id)).size,avg=tot?hrs/tot:0;doc.setFont('helvetica','bold');doc.setFontSize(18);doc.setTextColor(15,23,42);doc.text('Rezijska dela',m,y);y+=6;doc.setFont('helvetica','normal');doc.setFontSize(10);doc.setTextColor(100);doc.text('Belezenje ur dela v proizvodnji',m,y);y+=5;doc.setFontSize(9);doc.text('Obdobje: '+periodLabels[period]+' | Datum: '+new Date().toLocaleDateString('sl-SI'),m,y);y+=10;doc.setFillColor(248,250,252);doc.roundedRect(m,y,pW-2*m,16,2,2,'F');const kpis=[{l:'Evidenc',v:tot.toString()},{l:'Ur',v:hrs.toFixed(1)},{l:'Povprecje',v:avg.toFixed(1)+'h'},{l:'Zaposlenih',v:emp.toString()}];const kW=(pW-2*m)/4;kpis.forEach((k,i)=>{const x=m+i*kW+kW/2;doc.setFont('helvetica','bold');doc.setFontSize(14);doc.setTextColor(15,23,42);doc.text(k.v,x,y+7,{align:'center'});doc.setFont('helvetica','normal');doc.setFontSize(8);doc.setTextColor(100);doc.text(k.l,x,y+11,{align:'center'});});y+=22;doc.setFont('helvetica','bold');doc.setFontSize(11);doc.setTextColor(15,23,42);doc.text('Pregled po zaposlenih',m,y);y+=5;const byEmpPdf={};filtered.forEach(e=>{if(!byEmpPdf[e.employee.id])byEmpPdf[e.employee.id]={emp:e.employee,hrs:0,cnt:0};byEmpPdf[e.employee.id].hrs+=e.duration||0;byEmpPdf[e.employee.id].cnt++;});const empTableData=Object.values(byEmpPdf).sort((a,b)=>b.hrs-a.hrs).map(d=>{const loc=lokacije.find(l=>l.id===d.emp.location);return[d.emp.name||'Neznano',loc?.name||d.emp.location||'Neznano',d.emp.employmentType||'Neznano',d.cnt.toString(),d.hrs.toFixed(1)];});doc.autoTable({startY:y,head:[['Zaposleni','Lokacija','Tip','Evidenc','Ure']],body:empTableData,margin:{left:m,right:m},styles:{font:'helvetica',fontSize:8,cellPadding:2},headStyles:{fillColor:[15,23,42],fontStyle:'bold',textColor:255},alternateRowStyles:{fillColor:[248,250,252]},columnStyles:{0:{cellWidth:45},1:{cellWidth:35},2:{cellWidth:40},3:{cellWidth:20,halign:'right'},4:{cellWidth:20,halign:'right'}},tableWidth:pW-2*m});y=doc.lastAutoTable.finalY+8;if(y>pH-60){doc.addPage();y=m;}doc.setFont('helvetica','bold');doc.setFontSize(11);doc.setTextColor(15,23,42);doc.text('Pregled po delovnih nalogih',m,y);y+=5;const byDNPdf={};filtered.forEach(e=>{const id=e.dn.id;if(!byDNPdf[id])byDNPdf[id]={dn:e.dn,hrs:0,cnt:0,emps:new Set()};byDNPdf[id].hrs+=e.duration||0;byDNPdf[id].cnt++;byDNPdf[id].emps.add(e.employee.id);});const dnTableData=Object.values(byDNPdf).sort((a,b)=>b.hrs-a.hrs).map(d=>[d.dn.id||'',(d.dn.name||'').substring(0,40),d.cnt.toString(),d.hrs.toFixed(1),d.emps.size.toString()]);doc.autoTable({startY:y,head:[['ID','Naziv','Evidenc','Ure','Zaposlenih']],body:dnTableData,margin:{left:m,right:m},styles:{font:'helvetica',fontSize:8,cellPadding:2},headStyles:{fillColor:[15,23,42],fontStyle:'bold',textColor:255},alternateRowStyles:{fillColor:[248,250,252]},columnStyles:{0:{cellWidth:22},1:{cellWidth:75},2:{cellWidth:22,halign:'right'},3:{cellWidth:22,halign:'right'},4:{cellWidth:22,halign:'right'}},tableWidth:pW-2*m});y=doc.lastAutoTable.finalY+8;if(y>pH-50){doc.addPage();y=m;}doc.setFont('helvetica','bold');doc.setFontSize(11);doc.setTextColor(15,23,42);doc.text('Pregled po lokacijah',m,y);y+=5;const byLocPdf={};filtered.forEach(e=>{const loc=lokacije.find(l=>l.id===e.employee.location);const nm=loc?.name||e.employee.location||'Neznano';if(!byLocPdf[nm])byLocPdf[nm]={name:nm,hrs:0,cnt:0,emps:new Set()};byLocPdf[nm].hrs+=e.duration||0;byLocPdf[nm].cnt++;byLocPdf[nm].emps.add(e.employee.id);});const locTableData=Object.values(byLocPdf).sort((a,b)=>b.hrs-a.hrs).map(d=>{const avgDur=d.cnt?(d.hrs/d.cnt):0;return[d.name,d.cnt.toString(),d.hrs.toFixed(1),d.emps.size.toString(),avgDur.toFixed(1)];});doc.autoTable({startY:y,head:[['Lokacija','Evidenc','Ure','Zaposlenih','Povp.']],body:locTableData,margin:{left:m,right:m},styles:{font:'helvetica',fontSize:8,cellPadding:2},headStyles:{fillColor:[15,23,42],fontStyle:'bold',textColor:255},alternateRowStyles:{fillColor:[248,250,252]},columnStyles:{0:{cellWidth:50},1:{cellWidth:28,halign:'right'},2:{cellWidth:28,halign:'right'},3:{cellWidth:30,halign:'right'},4:{cellWidth:28,halign:'right'}},tableWidth:pW-2*m});const pageCount=doc.internal.getNumberOfPages();for(let i=1;i<=pageCount;i++){doc.setPage(i);doc.setFontSize(8);doc.setTextColor(150);doc.text('Stran '+i+' / '+pageCount,pW-m,pH-8,{align:'right'});doc.text('Rezijska dela - Belezenje ur',m,pH-8);doc.setDrawColor(200);doc.line(m,pH-12,pW-m,pH-12);}doc.save('rezijska-dela-'+new Date().toISOString().slice(0,10)+'.pdf');showToast('PDF uspesno izvezen');}
        function formatDuration(h){if(!h||isNaN(h))return'--';const hr=Math.floor(h),mn=Math.round((h-hr)*60);return mn>0?hr+'h '+mn+'m':hr+'h';}
        function showToast(msg){const t=document.getElementById('toast');t.textContent=msg;t.classList.add('active');setTimeout(()=>t.classList.remove('active'),3000);}
        document.addEventListener('click',e=>{if(!e.target.closest('.dn-selector'))document.getElementById('dn-selector').classList.remove('open');});
        document.addEventListener('DOMContentLoaded',()=>{loadData();});
    </script>
</body>
</html>
