<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebGIS SPBU — Layer Groups Control</title>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg:     #0f1923;
            --panel:  #172030;
            --panel2: #1c2a3a;
            --border: #263545;
            --accent: #f7c948;
            --green:  #22c55e;
            --red:    #ef4444;
            --text:   #e2eaf3;
            --muted:  #7a95ae;
            --radius: 10px;
            --shadow: 0 8px 32px rgba(0,0,0,.55);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* ── TOPBAR ── */
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            height: 54px;
            background: var(--panel);
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
            z-index: 900;
        }

        .brand { display: flex; align-items: center; gap: 10px; }

        .brand-icon {
            width: 32px; height: 32px;
            background: var(--accent);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 17px;
        }

        .brand-text {
            font-family: 'Space Mono', monospace;
            font-size: 13px; font-weight: 700;
            letter-spacing: 1px; color: var(--accent);
        }

        .brand-sub { font-size: 10px; color: var(--muted); letter-spacing: .5px; }

        .topbar-right { display: flex; align-items: center; gap: 10px; }

        /* Layer badge in topbar */
        .layer-badge {
            display: flex; align-items: center; gap: 8px;
            padding: 5px 12px;
            background: var(--panel2);
            border: 1px solid var(--border);
            border-radius: 99px;
            font-size: 11px; color: var(--muted);
        }
        .layer-badge-dot { width: 8px; height: 8px; border-radius: 50%; }
        .layer-badge span { font-weight: 600; }

        .btn-add {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 7px 14px; border-radius: 8px;
            font-size: 12px; font-weight: 700;
            text-decoration: none;
            background: var(--accent); color: #0f1923;
            transition: all .15s;
        }
        .btn-add:hover { background: #ffd95c; }

        /* ── LAYOUT ── */
        .layout {
            display: flex;
            flex: 1;
            overflow: hidden;
            height: calc(100vh - 54px);
        }

        /* ── SIDEBAR ── */
        .sidebar {
            width: 340px;
            flex-shrink: 0;
            background: var(--panel);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Stats */
        .stats-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }

        .stat {
            padding: 12px 10px;
            text-align: center;
            border-right: 1px solid var(--border);
        }
        .stat:last-child { border-right: none; }

        .stat-num {
            font-family: 'Space Mono', monospace;
            font-size: 22px; font-weight: 700;
        }
        .stat-num.all   { color: var(--accent); }
        .stat-num.open  { color: var(--green); }
        .stat-num.close { color: var(--red); }

        .stat-lbl {
            font-size: 10px; color: var(--muted);
            text-transform: uppercase; letter-spacing: .5px;
            margin-top: 2px;
        }

        /* ── LAYER TOGGLE PANEL ── */
        .layer-panel {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
            background: var(--panel2);
        }

        .layer-panel-title {
            font-size: 10px; font-weight: 700;
            color: var(--muted); text-transform: uppercase;
            letter-spacing: .7px; margin-bottom: 10px;
            display: flex; align-items: center; gap: 6px;
        }

        .layer-toggles {
            display: flex; flex-direction: column; gap: 8px;
        }

        .layer-toggle {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 12px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
            transition: all .15s;
            user-select: none;
        }
        .layer-toggle:hover { border-color: var(--accent); }
        .layer-toggle.active-green { border-color: rgba(34,197,94,.4); background: rgba(34,197,94,.06); }
        .layer-toggle.active-red   { border-color: rgba(239,68,68,.4);  background: rgba(239,68,68,.06); }

        .layer-toggle input[type="checkbox"] {
            width: 16px; height: 16px;
            accent-color: var(--accent);
            cursor: pointer; flex-shrink: 0;
        }

        .layer-toggle-ico {
            width: 28px; height: 28px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; flex-shrink: 0;
        }
        .layer-toggle-ico.green { background: rgba(34,197,94,.15); border: 1px solid rgba(34,197,94,.3); }
        .layer-toggle-ico.red   { background: rgba(239,68,68,.15);  border: 1px solid rgba(239,68,68,.3); }

        .layer-toggle-info { flex: 1; }
        .layer-toggle-label { font-size: 12px; font-weight: 600; }
        .layer-toggle-count {
            font-family: 'Space Mono', monospace;
            font-size: 10px; color: var(--muted); margin-top: 1px;
        }

        /* Search */
        .search-wrap {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }

        .search-box { position: relative; }

        .search-box input {
            width: 100%;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px 12px 8px 32px;
            color: var(--text);
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 13px; outline: none;
            transition: border-color .18s;
        }
        .search-box input:focus { border-color: var(--accent); }
        .search-box .s-ico { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: 13px; }

        /* Filter tabs */
        .filter-tabs {
            display: flex;
            padding: 8px 14px;
            gap: 6px;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }

        .tab-btn {
            flex: 1; padding: 5px 6px;
            border-radius: 6px;
            font-size: 11px; font-weight: 600;
            border: 1px solid var(--border);
            background: transparent; color: var(--muted);
            cursor: pointer; transition: all .15s;
        }
        .tab-btn:hover { border-color: var(--accent); color: var(--accent); }
        .tab-btn.active { background: var(--accent); color: #0f1923; border-color: var(--accent); }

        /* List */
        .data-list { flex: 1; overflow-y: auto; }
        .data-list::-webkit-scrollbar { width: 4px; }
        .data-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

        .data-item {
            display: flex; align-items: center; gap: 10px;
            padding: 11px 14px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: background .13s;
        }
        .data-item:hover { background: rgba(247,201,72,.04); }
        .data-item.active {
            background: rgba(247,201,72,.08);
            border-left: 3px solid var(--accent);
            padding-left: 11px;
        }

        .item-marker {
            width: 32px; height: 32px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px; flex-shrink: 0;
        }
        .item-marker.open  { background: rgba(34,197,94,.15); border: 1px solid rgba(34,197,94,.3); }
        .item-marker.close { background: rgba(239,68,68,.15);  border: 1px solid rgba(239,68,68,.3); }

        .item-info { flex: 1; min-width: 0; }

        .item-name {
            font-size: 13px; font-weight: 600;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        .item-nomor {
            font-family: 'Space Mono', monospace;
            font-size: 10px; color: var(--muted); margin-top: 1px;
        }

        .item-status {
            font-size: 10px; font-weight: 600;
            padding: 2px 8px; border-radius: 99px;
            margin-top: 4px; display: inline-block;
        }
        .item-status.open  { background: rgba(34,197,94,.15); color: var(--green); }
        .item-status.close { background: rgba(239,68,68,.15);  color: var(--red); }

        .item-actions { display: flex; flex-direction: column; gap: 4px; flex-shrink: 0; }

        .icon-btn {
            width: 28px; height: 28px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: transparent; color: var(--muted);
            font-size: 13px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: all .15s;
        }
        .icon-btn.edit:hover  { border-color: var(--accent); color: var(--accent); }
        .icon-btn.del:hover   { border-color: var(--red); color: var(--red); }

        .empty-state {
            padding: 50px 20px; text-align: center; color: var(--muted);
        }
        .empty-state .big { font-size: 40px; margin-bottom: 10px; }
        .empty-state p { font-size: 12px; }

        /* ── MAP ── */
        .map-wrap { flex: 1; display: flex; flex-direction: column; position: relative; }
        #map { flex: 1; width: 100%; height: 100%; }

        /* ── Leaflet Layer Control Custom Style ── */
        .leaflet-control-layers {
            background: var(--panel) !important;
            border: 1px solid var(--border) !important;
            border-radius: 10px !important;
            box-shadow: var(--shadow) !important;
            padding: 0 !important;
            min-width: 180px;
        }
        .leaflet-control-layers-toggle {
            background-color: var(--panel) !important;
        }
        .leaflet-control-layers-expanded {
            padding: 0 !important;
        }
        .leaflet-control-layers-list {
            padding: 8px 12px 10px !important;
        }
        .leaflet-control-layers-separator {
            border-top: 1px solid var(--border) !important;
            margin: 6px 0 !important;
        }
        .leaflet-control-layers label {
            color: var(--text) !important;
            font-family: 'Plus Jakarta Sans', sans-serif !important;
            font-size: 12px !important;
            font-weight: 600 !important;
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            padding: 4px 0 !important;
        }
        .leaflet-control-layers-base label span,
        .leaflet-control-layers-overlays label span {
            margin-left: 4px !important;
        }

        /* custom section headings inside layer control */
        .lc-section-head {
            font-family: 'Space Mono', monospace;
            font-size: 9px; font-weight: 700;
            color: var(--muted);
            text-transform: uppercase; letter-spacing: .6px;
            padding: 8px 12px 2px;
            border-bottom: 1px solid var(--border);
            display: block;
        }

        /* ── MODAL EDIT ── */
        .overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.65); z-index: 1000;
            backdrop-filter: blur(4px);
            align-items: center; justify-content: center;
        }
        .overlay.active { display: flex; }

        .modal {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 16px;
            width: 420px;
            max-width: calc(100vw - 24px);
            box-shadow: var(--shadow);
            animation: slideUp .2s ease;
        }

        @keyframes slideUp {
            from { opacity:0; transform:translateY(16px); }
            to   { opacity:1; transform:translateY(0); }
        }

        .modal-head {
            display: flex; align-items: center; justify-content: space-between;
            padding: 18px 22px 14px;
            border-bottom: 1px solid var(--border);
        }

        .modal-title {
            font-size: 15px; font-weight: 700;
            display: flex; align-items: center; gap: 8px;
        }

        .badge-edit {
            font-size: 10px; padding: 2px 8px; border-radius: 99px;
            background: rgba(247,201,72,.15); color: var(--accent);
            border: 1px solid rgba(247,201,72,.25);
        }

        .modal-close {
            background: none; border: none;
            color: var(--muted); font-size: 16px;
            cursor: pointer; padding: 4px;
            border-radius: 6px; transition: color .15s;
        }
        .modal-close:hover { color: var(--red); }

        .modal-body { padding: 18px 22px; }

        .coord-row {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 8px; margin-bottom: 12px;
        }

        .coord-chip {
            background: var(--bg); border: 1px solid var(--border);
            border-radius: 8px; padding: 8px 10px;
        }
        .coord-chip label {
            display: block; font-size: 9px;
            font-family: 'Space Mono', monospace;
            color: var(--accent); letter-spacing: .5px;
            text-transform: uppercase; margin-bottom: 3px;
        }
        .coord-chip span { font-family: 'Space Mono', monospace; font-size: 11px; }

        .drag-hint {
            text-align: center; font-size: 11px; color: var(--muted);
            padding: 6px 10px; background: var(--bg);
            border: 1px dashed var(--border); border-radius: 6px;
            margin-bottom: 12px;
        }

        .form-group { margin-bottom: 12px; }
        .form-group label {
            display: block; font-size: 11px; font-weight: 600;
            color: var(--muted); text-transform: uppercase;
            letter-spacing: .5px; margin-bottom: 5px;
        }
        .form-group input,
        .form-group select {
            width: 100%; background: var(--bg);
            border: 1px solid var(--border); border-radius: var(--radius);
            padding: 9px 12px; color: var(--text);
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 13px; outline: none; transition: border-color .18s;
        }
        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(247,201,72,.1);
        }
        .form-group select option { background: #172030; }

        .modal-foot {
            display: flex; gap: 8px;
            padding: 14px 22px 18px;
            border-top: 1px solid var(--border);
        }

        .btn {
            flex: 1; display: inline-flex; align-items: center;
            justify-content: center; gap: 6px; padding: 10px;
            border-radius: 8px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 13px; font-weight: 600;
            cursor: pointer; border: none; transition: all .18s;
        }
        .btn-primary { background: var(--accent); color: #0f1923; }
        .btn-primary:hover { background: #ffd95c; }
        .btn-primary:disabled { opacity: .5; cursor: not-allowed; }
        .btn-ghost {
            background: transparent; border: 1px solid var(--border); color: var(--muted);
        }
        .btn-ghost:hover { border-color: var(--red); color: var(--red); }

        /* ── CONFIRM ── */
        .confirm-modal {
            background: var(--panel); border: 1px solid var(--border);
            border-radius: 14px; width: 320px;
            max-width: calc(100vw - 24px);
            padding: 28px 24px 20px; text-align: center;
            box-shadow: var(--shadow); animation: slideUp .2s ease;
        }
        .confirm-icon { font-size: 38px; margin-bottom: 10px; }
        .confirm-title { font-size: 15px; font-weight: 700; margin-bottom: 6px; }
        .confirm-msg { font-size: 12px; color: var(--muted); margin-bottom: 18px; }
        .confirm-btns { display: flex; gap: 8px; }

        /* ── TOAST ── */
        .toast {
            position: fixed; bottom: 24px; right: 20px; z-index: 2000;
            background: var(--panel); border: 1px solid var(--border);
            border-radius: 10px; padding: 12px 16px;
            display: flex; align-items: center; gap: 10px;
            font-size: 13px; box-shadow: var(--shadow);
            transform: translateX(120%);
            transition: transform .3s cubic-bezier(.34,1.56,.64,1);
            min-width: 220px;
        }
        .toast.show { transform: translateX(0); }
        .toast.success { border-color: rgba(34,197,94,.3); }
        .toast.error   { border-color: rgba(239,68,68,.3); }

        /* Leaflet popup */
        .leaflet-popup-content-wrapper {
            background: #172030 !important; border: 1px solid #263545 !important;
            border-radius: 10px !important; color: #e2eaf3 !important;
            box-shadow: 0 8px 24px rgba(0,0,0,.5) !important;
        }
        .leaflet-popup-tip { background: #172030 !important; }
        .leaflet-popup-content { margin: 12px 14px !important; }
    </style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
    <div class="brand">
        <div class="brand-icon">⛽</div>
        <div>
            <div class="brand-text">WEBGIS SPBU</div>
            <div class="brand-sub">Layer Groups &amp; Control</div>
        </div>
    </div>
    <div class="topbar-right">
        <div class="layer-badge">
            <div class="layer-badge-dot" style="background:var(--green)"></div>
            <span id="badgeCount24">0</span> Buka 24 Jam
            &nbsp;·&nbsp;
            <div class="layer-badge-dot" style="background:var(--red)"></div>
            <span id="badgeCountTidak">0</span> Tidak 24 Jam
        </div>
        <a href="insert.php" class="btn-add">➕ Tambah SPBU</a>
    </div>
</div>

<!-- LAYOUT -->
<div class="layout">

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="stats-row">
            <div class="stat">
                <div class="stat-num all" id="statTotal">0</div>
                <div class="stat-lbl">Total</div>
            </div>
            <div class="stat">
                <div class="stat-num open" id="stat24">0</div>
                <div class="stat-lbl">24 Jam</div>
            </div>
            <div class="stat">
                <div class="stat-num close" id="statTidak">0</div>
                <div class="stat-lbl">Tidak</div>
            </div>
        </div>

        <!-- LAYER CONTROL PANEL (sidebar) -->
        <div class="layer-panel">
            <div class="layer-panel-title">
                🗺️ Layer Groups Control
            </div>
            <div class="layer-toggles">
                <label class="layer-toggle active-green" id="toggle24Label">
                    <input type="checkbox" id="toggle24" checked onchange="toggleLayer24()">
                    <div class="layer-toggle-ico green">⛽</div>
                    <div class="layer-toggle-info">
                        <div class="layer-toggle-label" style="color:var(--green)">SPBU Buka 24 Jam</div>
                        <div class="layer-toggle-count" id="count24Label">0 titik lokasi</div>
                    </div>
                </label>
                <label class="layer-toggle active-red" id="toggleTidakLabel">
                    <input type="checkbox" id="toggleTidak" checked onchange="toggleLayerTidak()">
                    <div class="layer-toggle-ico red">⛽</div>
                    <div class="layer-toggle-info">
                        <div class="layer-toggle-label" style="color:var(--red)">SPBU Tidak 24 Jam</div>
                        <div class="layer-toggle-count" id="countTidakLabel">0 titik lokasi</div>
                    </div>
                </label>
            </div>
        </div>

        <!-- SEARCH -->
        <div class="search-wrap">
            <div class="search-box">
                <span class="s-ico">🔍</span>
                <input type="text" id="searchInput" placeholder="Cari nama atau nomor SPBU...">
            </div>
        </div>

        <!-- FILTER TABS -->
        <div class="filter-tabs">
            <button class="tab-btn active" id="tabAll" onclick="setTab('all')">Semua</button>
            <button class="tab-btn" id="tab24" onclick="setTab('24')">24 Jam</button>
            <button class="tab-btn" id="tabTidak" onclick="setTab('tidak')">Tidak</button>
        </div>

        <div class="data-list" id="dataList">
            <div class="empty-state">
                <div class="big">⏳</div>
                <p>Memuat data...</p>
            </div>
        </div>
    </div>

    <!-- MAP -->
    <div class="map-wrap">
        <div id="map"></div>
    </div>

</div>

<!-- MODAL EDIT -->
<div class="overlay" id="overlay">
    <div class="modal">
        <div class="modal-head">
            <div class="modal-title">
                ✏️ Edit Data SPBU
                <span class="badge-edit">EDIT</span>
            </div>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <div class="modal-body">
            <div class="coord-row">
                <div class="coord-chip">
                    <label>Latitude</label>
                    <span id="dispLat">—</span>
                </div>
                <div class="coord-chip">
                    <label>Longitude</label>
                    <span id="dispLng">—</span>
                </div>
            </div>
            <div class="drag-hint">↕️ Geser marker di peta untuk mengubah koordinat</div>
            <div class="form-group">
                <label>Nama SPBU</label>
                <input type="text" id="fNama" placeholder="Nama SPBU" autocomplete="off">
            </div>
            <div class="form-group">
                <label>Nomor SPBU</label>
                <input type="text" id="fNomor" placeholder="Nomor SPBU" autocomplete="off">
            </div>
            <div class="form-group">
                <label>Status Operasional</label>
                <select id="fStatus">
                    <option value="">— Pilih Status —</option>
                    <option value="buka 24 jam">✅ Buka 24 Jam</option>
                    <option value="tidak">🔴 Tidak 24 Jam</option>
                </select>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-ghost" onclick="closeModal()">Batal</button>
            <button class="btn btn-primary" id="btnUpdate" onclick="updateData()">💾 Simpan Perubahan</button>
        </div>
    </div>
</div>

<!-- CONFIRM DELETE -->
<div class="overlay" id="overlayConfirm">
    <div class="confirm-modal">
        <div class="confirm-icon">🗑️</div>
        <div class="confirm-title">Hapus Data SPBU?</div>
        <div class="confirm-msg" id="confirmMsg">Data ini akan dihapus permanen.</div>
        <div class="confirm-btns">
            <button class="btn btn-ghost" style="flex:1" onclick="closeConfirm()">Batal</button>
            <button class="btn btn-primary" style="flex:1;background:var(--red);color:#fff" onclick="confirmDelete()">Hapus</button>
        </div>
    </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast">
    <span id="toastIco"></span>
    <span id="toastMsg"></span>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// ══════════════════════════════════════════════════════
// MAP INIT
// ══════════════════════════════════════════════════════
const map = L.map('map', {
    center: [-0.0263, 109.3425],
    zoom: 13,
    zoomControl: false
});
L.control.zoom({ position: 'bottomright' }).addTo(map);

// ── BASE LAYERS ───────────────────────────────────────
const layerOSM = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 19
});
const layerSat = L.tileLayer(
    'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
    { attribution: '© Esri', maxZoom: 19 }
);
const layerHOT = L.tileLayer('https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap HOT', maxZoom: 19
});
layerOSM.addTo(map);

// ══════════════════════════════════════════════════════
// LAYER GROUPS — Konsep utama implementasi
// Dua LayerGroup terpisah untuk 2 jenis SPBU:
//   layer24   → SPBU Buka 24 Jam  (marker hijau)
//   layerTidak → SPBU Tidak 24 Jam (marker merah)
// ══════════════════════════════════════════════════════
const layer24    = L.layerGroup().addTo(map);   // default: tampil
const layerTidak = L.layerGroup().addTo(map);   // default: tampil

// ── BASE LAYERS MAP ───────────────────────────────────
const baseLayers = {
    '🗺️ Street Map': layerOSM,
    '🌍 Satelit':    layerSat,
    '🔴 HOT OSM':    layerHOT
};

// ── OVERLAY LAYERS MAP (Layer Groups SPBU) ────────────
const overlayLayers = {
    '✅ SPBU Buka 24 Jam':  layer24,
    '🔴 SPBU Tidak 24 Jam': layerTidak
};

// ── LAYER CONTROL (Leaflet built-in) ──────────────────
// Ini adalah implementasi resmi Leaflet Layer Control
// Muncul di pojok kanan atas peta sebagai widget
const leafletLayerControl = L.control.layers(baseLayers, overlayLayers, {
    position: 'topright',
    collapsed: false   // Selalu expanded agar mudah dilihat
}).addTo(map);

// Sync Leaflet layer control events dengan sidebar checkbox
map.on('overlayadd', function(e) {
    if (e.layer === layer24)    { document.getElementById('toggle24').checked    = true; updateToggleStyle(); }
    if (e.layer === layerTidak) { document.getElementById('toggleTidak').checked = true; updateToggleStyle(); }
    renderList(getFilteredList());
});
map.on('overlayremove', function(e) {
    if (e.layer === layer24)    { document.getElementById('toggle24').checked    = false; updateToggleStyle(); }
    if (e.layer === layerTidak) { document.getElementById('toggleTidak').checked = false; updateToggleStyle(); }
    renderList(getFilteredList());
});

setTimeout(() => map.invalidateSize(), 200);

// ══════════════════════════════════════════════════════
// STATE
// ══════════════════════════════════════════════════════
let allData  = [];
let markers  = {};         // { id: L.marker }
let editId   = null;
let deleteId = null;
let activeId = null;
let editLat  = null;
let editLng  = null;
let currentTab = 'all';    // 'all' | '24' | 'tidak'

// ══════════════════════════════════════════════════════
// ICON FACTORY
// ══════════════════════════════════════════════════════
function makeIcon(status, highlighted = false) {
    const color = status === 'buka 24 jam' ? '#22c55e' : '#ef4444';
    const glow  = highlighted
        ? `box-shadow:0 0 0 5px rgba(247,201,72,.5),0 4px 14px ${color}88;`
        : `box-shadow:0 4px 10px ${color}66;`;
    return L.divIcon({
        className: '',
        html: `<div style="
            width:36px;height:36px;
            background:${color};
            border:3px solid #fff;
            border-radius:50% 50% 50% 0;
            transform:rotate(-45deg);
            ${glow}
            display:flex;align-items:center;justify-content:center;
        "><span style="transform:rotate(45deg);font-size:15px">⛽</span></div>`,
        iconSize: [36, 42],
        iconAnchor: [18, 42],
        popupAnchor: [0, -44]
    });
}

// ══════════════════════════════════════════════════════
// POPUP HTML
// ══════════════════════════════════════════════════════
function popupHtml(row) {
    const is24   = row.status === 'buka 24 jam';
    const sColor = is24 ? '#22c55e' : '#ef4444';
    const layerLabel = is24 ? '✅ Layer: Buka 24 Jam' : '🔴 Layer: Tidak 24 Jam';
    return `
        <div style="font-family:'Plus Jakarta Sans',sans-serif;min-width:200px">
            <div style="font-size:15px;font-weight:700;margin-bottom:4px">⛽ ${esc(row.nama)}</div>
            <div style="font-family:monospace;font-size:11px;color:#7a95ae;margin-bottom:6px">${esc(row.nomor_spbu)}</div>
            <div style="display:inline-block;padding:2px 10px;border-radius:99px;font-size:11px;font-weight:600;
                background:${sColor}22;color:${sColor};border:1px solid ${sColor}44;margin-bottom:4px">
                ${is24 ? '✅ Buka 24 Jam' : '🔴 Tidak 24 Jam'}
            </div>
            <div style="font-size:10px;color:#4a6070;margin-bottom:6px">${layerLabel}</div>
            <hr style="border-color:#263545;margin:6px 0">
            <div style="font-family:monospace;font-size:10px;color:#4a6070">
                ${parseFloat(row.latitude).toFixed(6)}, ${parseFloat(row.longitude).toFixed(6)}
            </div>
            <div style="display:flex;gap:6px;margin-top:10px">
                <button onclick="openEdit(${row.id})" style="
                    flex:1;padding:5px;border-radius:6px;border:1px solid #263545;
                    background:transparent;color:#f7c948;font-size:11px;font-weight:600;cursor:pointer">
                    ✏️ Edit
                </button>
                <button onclick="openDelete(${row.id},'${esc(row.nama)}')" style="
                    flex:1;padding:5px;border-radius:6px;border:1px solid #263545;
                    background:transparent;color:#ef4444;font-size:11px;font-weight:600;cursor:pointer">
                    🗑️ Hapus
                </button>
            </div>
        </div>`;
}

// ══════════════════════════════════════════════════════
// ADD MARKER — masukkan ke LayerGroup yang sesuai
// ══════════════════════════════════════════════════════
function addMarker(row) {
    const is24      = row.status === 'buka 24 jam';
    const targetLayer = is24 ? layer24 : layerTidak;  // ← pilih layer group berdasar status

    const m = L.marker([row.latitude, row.longitude], {
        icon: makeIcon(row.status),
        draggable: true
    });

    m.bindPopup(popupHtml(row));
    m.addTo(targetLayer);   // ← tambah ke layer group, bukan langsung ke map

    m.on('click', function() {
        activeId = row.id;
        renderList(getFilteredList());
        const el = document.querySelector(`[data-id="${row.id}"]`);
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    m.on('dragend', async function() {
        const p  = m.getLatLng();
        const fd = new FormData();
        fd.append('action',    'update_coords');
        fd.append('id',        row.id);
        fd.append('latitude',  p.lat);
        fd.append('longitude', p.lng);
        try {
            const res  = await fetch('api.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                row.latitude = p.lat; row.longitude = p.lng;
                m.setPopupContent(popupHtml(row));
                const idx = allData.findIndex(r => r.id == row.id);
                if (idx >= 0) { allData[idx].latitude = p.lat; allData[idx].longitude = p.lng; }
                showToast('Koordinat diperbarui', 'success');
            } else {
                m.setLatLng([row.latitude, row.longitude]);
                showToast('Gagal update koordinat', 'error');
            }
        } catch(e) { showToast('Error: ' + e.message, 'error'); }
    });

    markers[row.id] = { marker: m, layer: targetLayer };
}

// ══════════════════════════════════════════════════════
// LAYER TOGGLE — dari sidebar checkbox
// ══════════════════════════════════════════════════════
function toggleLayer24() {
    const cb = document.getElementById('toggle24');
    if (cb.checked) {
        map.addLayer(layer24);
        showToast('Layer SPBU 24 Jam ditampilkan', 'success');
    } else {
        map.removeLayer(layer24);
        showToast('Layer SPBU 24 Jam disembunyikan', 'success');
    }
    updateToggleStyle();
    renderList(getFilteredList());
}

function toggleLayerTidak() {
    const cb = document.getElementById('toggleTidak');
    if (cb.checked) {
        map.addLayer(layerTidak);
        showToast('Layer SPBU Tidak 24 Jam ditampilkan', 'success');
    } else {
        map.removeLayer(layerTidak);
        showToast('Layer SPBU Tidak 24 Jam disembunyikan', 'success');
    }
    updateToggleStyle();
    renderList(getFilteredList());
}

function updateToggleStyle() {
    const cb24    = document.getElementById('toggle24');
    const cbTidak = document.getElementById('toggleTidak');
    const lbl24   = document.getElementById('toggle24Label');
    const lblTidak= document.getElementById('toggleTidakLabel');

    lbl24.style.opacity    = cb24.checked    ? '1' : '0.45';
    lblTidak.style.opacity = cbTidak.checked ? '1' : '0.45';
}

// ══════════════════════════════════════════════════════
// TAB FILTER (sidebar list only)
// ══════════════════════════════════════════════════════
function setTab(tab) {
    currentTab = tab;
    ['tabAll','tab24','tabTidak'].forEach(id => document.getElementById(id).classList.remove('active'));
    document.getElementById(tab === 'all' ? 'tabAll' : tab === '24' ? 'tab24' : 'tabTidak').classList.add('active');
    renderList(getFilteredList());
}

// ══════════════════════════════════════════════════════
// RENDER LIST
// ══════════════════════════════════════════════════════
function getFilteredList() {
    const q   = document.getElementById('searchInput').value.toLowerCase();
    const show24    = document.getElementById('toggle24').checked;
    const showTidak = document.getElementById('toggleTidak').checked;

    return allData.filter(r => {
        const is24 = r.status === 'buka 24 jam';
        // respect tab
        if (currentTab === '24'    && !is24)  return false;
        if (currentTab === 'tidak' &&  is24)  return false;
        // respect layer visibility
        if (is24  && !show24)    return false;
        if (!is24 && !showTidak) return false;
        // search
        if (q && !r.nama.toLowerCase().includes(q) && !r.nomor_spbu.toLowerCase().includes(q)) return false;
        return true;
    });
}

function renderList(data) {
    const el = document.getElementById('dataList');
    if (!data.length) {
        el.innerHTML = `<div class="empty-state"><div class="big">⛽</div><p>Tidak ada data SPBU pada layer aktif</p></div>`;
        return;
    }
    el.innerHTML = data.map(row => {
        const is24 = row.status === 'buka 24 jam';
        return `
        <div class="data-item ${activeId == row.id ? 'active' : ''}"
             data-id="${row.id}" onclick="focusMarker(${row.id})">
            <div class="item-marker ${is24 ? 'open' : 'close'}">⛽</div>
            <div class="item-info">
                <div class="item-name">${esc(row.nama)}</div>
                <div class="item-nomor">${esc(row.nomor_spbu)}</div>
                <span class="item-status ${is24 ? 'open' : 'close'}">
                    ${is24 ? '✅ 24 Jam' : '🔴 Tidak'}
                </span>
            </div>
            <div class="item-actions">
                <button class="icon-btn edit" title="Edit"
                    onclick="event.stopPropagation();openEdit(${row.id})">✏️</button>
                <button class="icon-btn del" title="Hapus"
                    onclick="event.stopPropagation();openDelete(${row.id},'${esc(row.nama)}')">🗑️</button>
            </div>
        </div>`;
    }).join('');
}

// ══════════════════════════════════════════════════════
// FOCUS MARKER
// ══════════════════════════════════════════════════════
function focusMarker(id) {
    activeId = id;
    renderList(getFilteredList());
    const row = allData.find(r => r.id == id);
    if (!row) return;
    map.flyTo([row.latitude, row.longitude], 16, { duration: 1 });
    setTimeout(() => {
        if (markers[id]) markers[id].marker.openPopup();
    }, 900);
}

// ══════════════════════════════════════════════════════
// LOAD DATA
// ══════════════════════════════════════════════════════
async function loadData() {
    try {
        const res  = await fetch('api.php?action=all');
        const data = await res.json();
        if (!data.success) throw new Error(data.message);
        allData = data.data;

        updateStats();
        renderList(getFilteredList());

        // Clear semua marker dari kedua layer groups
        layer24.clearLayers();
        layerTidak.clearLayers();
        markers = {};

        // Tambahkan marker ke layer group yang sesuai
        allData.forEach(addMarker);

        if (allData.length) {
            const bounds = L.latLngBounds(allData.map(r => [r.latitude, r.longitude]));
            map.fitBounds(bounds.pad(0.15));
        }
    } catch(e) {
        document.getElementById('dataList').innerHTML =
            `<div class="empty-state"><div class="big">⚠️</div><p>${e.message}</p></div>`;
    }
}

function updateStats() {
    const total = allData.length;
    const jam24 = allData.filter(r => r.status === 'buka 24 jam').length;
    const tidak = total - jam24;

    document.getElementById('statTotal').textContent      = total;
    document.getElementById('stat24').textContent         = jam24;
    document.getElementById('statTidak').textContent      = tidak;
    document.getElementById('badgeCount24').textContent   = jam24;
    document.getElementById('badgeCountTidak').textContent= tidak;
    document.getElementById('count24Label').textContent   = jam24 + ' titik lokasi';
    document.getElementById('countTidakLabel').textContent= tidak + ' titik lokasi';
}

document.getElementById('searchInput').addEventListener('input', () => renderList(getFilteredList()));

// ══════════════════════════════════════════════════════
// OPEN EDIT
// ══════════════════════════════════════════════════════
function openEdit(id) {
    map.closePopup();
    const row = allData.find(r => r.id == id);
    if (!row) return;

    editId  = id;
    editLat = parseFloat(row.latitude);
    editLng = parseFloat(row.longitude);

    document.getElementById('dispLat').textContent = editLat.toFixed(7);
    document.getElementById('dispLng').textContent = editLng.toFixed(7);
    document.getElementById('fNama').value   = row.nama;
    document.getElementById('fNomor').value  = row.nomor_spbu;
    document.getElementById('fStatus').value = row.status;

    if (markers[id]) {
        const m = markers[id].marker;
        m.setIcon(makeIcon(row.status, true));
        m.dragging.enable();
        m.off('dragend');
        m.on('dragend', function() {
            const p = m.getLatLng();
            editLat = p.lat; editLng = p.lng;
            document.getElementById('dispLat').textContent = p.lat.toFixed(7);
            document.getElementById('dispLng').textContent = p.lng.toFixed(7);
        });
    }

    document.getElementById('overlay').classList.add('active');
}

function closeModal() {
    document.getElementById('overlay').classList.remove('active');
    if (editId && markers[editId]) {
        const row = allData.find(r => r.id == editId);
        if (row) {
            markers[editId].marker.setIcon(makeIcon(row.status, false));
            markers[editId].marker.setLatLng([row.latitude, row.longitude]);
        }
    }
    editId = null;
}

// ══════════════════════════════════════════════════════
// UPDATE DATA
// ══════════════════════════════════════════════════════
async function updateData() {
    const nama   = document.getElementById('fNama').value.trim();
    const nomor  = document.getElementById('fNomor').value.trim();
    const status = document.getElementById('fStatus').value;

    if (!nama)   { showToast('Nama SPBU wajib diisi!', 'error'); return; }
    if (!nomor)  { showToast('Nomor SPBU wajib diisi!', 'error'); return; }
    if (!status) { showToast('Status wajib dipilih!', 'error'); return; }

    const btn = document.getElementById('btnUpdate');
    btn.disabled = true; btn.textContent = '⏳ Menyimpan...';

    const fd = new FormData();
    fd.append('action',     'update');
    fd.append('id',         editId);
    fd.append('nama',       nama);
    fd.append('nomor_spbu', nomor);
    fd.append('status',     status);
    fd.append('latitude',   editLat);
    fd.append('longitude',  editLng);

    try {
        const res  = await fetch('api.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showToast('Data berhasil diupdate!', 'success');
            document.getElementById('overlay').classList.remove('active');
            editId = null;
            await loadData(); // reload & realokasi ke layer group yang tepat
        } else {
            showToast('Gagal: ' + data.message, 'error');
        }
    } catch(e) {
        showToast('Error: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = '💾 Simpan Perubahan';
    }
}

// ══════════════════════════════════════════════════════
// DELETE
// ══════════════════════════════════════════════════════
function openDelete(id, nama) {
    map.closePopup();
    deleteId = id;
    document.getElementById('confirmMsg').textContent = `"${nama}" akan dihapus permanen.`;
    document.getElementById('overlayConfirm').classList.add('active');
}

function closeConfirm() {
    document.getElementById('overlayConfirm').classList.remove('active');
    deleteId = null;
}

async function confirmDelete() {
    if (!deleteId) return;
    const fd = new FormData();
    fd.append('action', 'delete');
    fd.append('id', deleteId);
    try {
        const res  = await fetch('api.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showToast('Data berhasil dihapus', 'success');
            closeConfirm();
            await loadData();
        } else {
            showToast('Gagal: ' + data.message, 'error');
        }
    } catch(e) { showToast('Error: ' + e.message, 'error'); }
}

// ══════════════════════════════════════════════════════
// TOAST
// ══════════════════════════════════════════════════════
let toastTimer = null;
function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    document.getElementById('toastIco').textContent = type === 'success' ? '✅' : '❌';
    document.getElementById('toastMsg').textContent = msg;
    t.className = `toast ${type} show`;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), 3200);
}

function esc(str) {
    return String(str || '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;')
        .replace(/'/g,'&#39;');
}

// ══════════════════════════════════════════════════════
// INIT
// ══════════════════════════════════════════════════════
loadData();
</script>
</body>
</html>
