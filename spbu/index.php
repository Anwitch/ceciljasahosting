<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebGIS SPBU — Manajemen Lokasi</title>
    <meta name="description" content="Sistem Informasi Geografis SPBU — lihat, tambah, edit, dan hapus data lokasi SPBU dalam satu halaman.">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <style>
        /* ── DESIGN TOKENS ── */
        :root {
            --bg:       #111827;
            --panel:    #1a2332;
            --panel2:   #1f2d3d;
            --border:   #263344;
            --accent:   #3b82f6;
            --accent-s: rgba(59,130,246,.1);
            --green:    #10b981;
            --green-s:  rgba(16,185,129,.12);
            --red:      #f43f5e;
            --red-s:    rgba(244,63,94,.12);
            --text:     #e2e8f0;
            --text2:    #94a3b8;
            --muted:    #64748b;
            --radius:   8px;
            --shadow:   0 8px 32px rgba(0,0,0,.5);
            --font:     'Inter', system-ui, sans-serif;
            --mono:     'JetBrains Mono', monospace;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--font);
            background: var(--bg);
            color: var(--text);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            font-size: 14px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        /* ── TOPBAR ── */
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 20px;
            height: 50px;
            background: var(--panel);
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
            z-index: 900;
        }

        .brand { display: flex; align-items: center; gap: 10px; }

        .brand-icon {
            width: 30px; height: 30px;
            background: var(--accent);
            border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px;
        }

        .brand-text {
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            letter-spacing: .1px;
        }

        .brand-sub {
            font-size: 11px;
            color: var(--muted);
            margin-top: 1px;
        }

        /* Legend */
        .legend {
            display: flex; align-items: center; gap: 16px;
            font-size: 12px; color: var(--text2);
        }
        .legend-item { display: flex; align-items: center; gap: 6px; }
        .legend-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
        }

        /* ── LAYOUT ── */
        .layout {
            display: flex;
            flex: 1;
            overflow: hidden;
            height: calc(100vh - 50px);
        }

        /* ── SIDEBAR ── */
        .sidebar {
            width: 320px;
            flex-shrink: 0;
            background: var(--panel);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* ── TAB BAR ── */
        .tab-bar {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }

        .tab-btn {
            padding: 12px 0;
            background: transparent;
            border: none;
            color: var(--muted);
            font-family: var(--font);
            font-size: 12.5px;
            font-weight: 500;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 6px;
            position: relative;
            transition: color .15s;
        }

        .tab-btn::after {
            content: '';
            position: absolute; bottom: 0; left: 16px; right: 16px;
            height: 2px;
            background: var(--accent);
            border-radius: 2px 2px 0 0;
            transform: scaleX(0);
            transition: transform .2s;
        }

        .tab-btn.active { color: var(--text); }
        .tab-btn.active::after { transform: scaleX(1); }
        .tab-btn:hover:not(.active) { color: var(--text2); }

        /* ── TAB PANELS ── */
        .tab-panel { display: none; flex-direction: column; flex: 1; overflow: hidden; }
        .tab-panel.active { display: flex; }

        /* ─ Stats ─ */
        .stats-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }

        .stat {
            padding: 12px 8px;
            text-align: center;
            border-right: 1px solid var(--border);
        }
        .stat:last-child { border-right: none; }

        .stat-num {
            font-family: var(--mono);
            font-size: 20px;
            font-weight: 700;
            line-height: 1;
        }
        .stat-num.all   { color: var(--text); }
        .stat-num.open  { color: var(--green); }
        .stat-num.close { color: var(--red); }

        .stat-lbl {
            font-size: 10px;
            color: var(--muted);
            margin-top: 4px;
            letter-spacing: .3px;
        }

        /* ─ Search ─ */
        .search-wrap {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }

        .search-box { position: relative; }

        .search-box input {
            width: 100%;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 7px 11px 7px 32px;
            color: var(--text);
            font-family: var(--font);
            font-size: 13px;
            outline: none;
            transition: border-color .15s;
        }
        .search-box input::placeholder { color: var(--muted); }
        .search-box input:focus { border-color: var(--accent); }
        .search-icon {
            position: absolute; left: 10px; top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            pointer-events: none;
        }
        .search-icon svg { display: block; }

        /* ─ List ─ */
        .data-list { flex: 1; overflow-y: auto; }
        .data-list::-webkit-scrollbar { width: 3px; }
        .data-list::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

        .data-item {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: background .12s;
        }
        .data-item:hover { background: rgba(255,255,255,.03); }
        .data-item.active {
            background: var(--accent-s);
            border-left: 2px solid var(--accent);
            padding-left: 10px;
        }

        .item-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
            margin-top: 1px;
        }

        .item-info { flex: 1; min-width: 0; }

        .item-name {
            font-size: 13px;
            font-weight: 500;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: var(--text);
        }

        .item-meta {
            display: flex; align-items: center; gap: 8px;
            margin-top: 2px;
        }

        .item-nomor {
            font-family: var(--mono);
            font-size: 10.5px;
            color: var(--muted);
        }

        .item-badge {
            font-size: 10px;
            font-weight: 500;
            padding: 1px 7px;
            border-radius: 4px;
        }
        .item-badge.open  { background: var(--green-s); color: var(--green); }
        .item-badge.close { background: var(--red-s);   color: var(--red); }

        .item-actions { display: flex; gap: 4px; flex-shrink: 0; }

        .icon-btn {
            width: 26px; height: 26px;
            border-radius: 5px;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--muted);
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: all .13s;
        }
        .icon-btn svg { display: block; }
        .icon-btn.edit:hover  { border-color: var(--accent); color: var(--accent); background: var(--accent-s); }
        .icon-btn.del:hover   { border-color: var(--red); color: var(--red); background: var(--red-s); }

        .empty-state {
            padding: 48px 20px;
            text-align: center;
            color: var(--muted);
        }
        .empty-state svg { opacity: .35; margin-bottom: 12px; }
        .empty-state p { font-size: 13px; }

        /* ─ Form Panel ─ */
        .form-header {
            padding: 14px 16px 12px;
            border-bottom: 1px solid var(--border);
            flex-shrink: 0;
        }

        .form-header h2 {
            font-size: 12px;
            font-weight: 600;
            color: var(--text2);
            text-transform: uppercase;
            letter-spacing: .6px;
            margin-bottom: 3px;
        }

        .form-header p {
            font-size: 12px;
            color: var(--muted);
            line-height: 1.5;
        }

        .form-body {
            padding: 14px 16px;
            flex: 1;
            overflow-y: auto;
        }
        .form-body::-webkit-scrollbar { width: 3px; }
        .form-body::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }

        /* Coord chips */
        .coord-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            margin-bottom: 10px;
        }

        .coord-chip {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 7px 10px;
        }

        .coord-chip label {
            display: block;
            font-size: 9.5px;
            font-family: var(--mono);
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .4px;
            margin-bottom: 2px;
        }

        .coord-chip span {
            font-family: var(--mono);
            font-size: 11px;
            color: var(--text2);
        }

        .click-hint {
            text-align: center;
            font-size: 11.5px;
            color: var(--muted);
            padding: 8px 10px;
            background: var(--bg);
            border: 1px dashed var(--border);
            border-radius: 6px;
            margin-bottom: 12px;
            transition: all .2s;
            line-height: 1.4;
        }
        .click-hint.ready {
            border-color: var(--green);
            color: var(--green);
        }

        /* Form fields */
        .form-group { margin-bottom: 10px; }

        .form-group label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            color: var(--text2);
            margin-bottom: 5px;
            letter-spacing: .2px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 8px 11px;
            color: var(--text);
            font-family: var(--font);
            font-size: 13px;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }
        .form-group input::placeholder { color: var(--muted); }
        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(59,130,246,.12);
        }
        .form-group select option { background: var(--panel); }

        .divider-line {
            height: 1px;
            background: var(--border);
            margin: 12px 0;
        }

        .btn-simpan {
            width: 100%;
            padding: 9px;
            border-radius: var(--radius);
            background: var(--accent);
            color: #fff;
            font-family: var(--font);
            font-size: 13px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: opacity .15s, transform .15s;
            display: flex; align-items: center; justify-content: center; gap: 6px;
        }
        .btn-simpan:hover { opacity: .88; }
        .btn-simpan:disabled { opacity: .45; cursor: not-allowed; }

        .btn-reset {
            width: 100%;
            padding: 8px;
            border-radius: var(--radius);
            background: transparent;
            border: 1px solid var(--border);
            color: var(--muted);
            font-family: var(--font);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            margin-top: 6px;
            transition: border-color .15s, color .15s;
        }
        .btn-reset:hover { border-color: var(--text2); color: var(--text); }

        /* Insert mode badge */
        .insert-mode-badge {
            display: none;
            position: absolute;
            top: 12px; left: 50%; transform: translateX(-50%);
            z-index: 700;
            background: rgba(17,24,39,.85);
            border: 1px solid var(--accent);
            backdrop-filter: blur(8px);
            padding: 5px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            color: var(--accent);
            pointer-events: none;
            white-space: nowrap;
        }
        .insert-mode-badge.visible { display: block; }

        /* ── MAP ── */
        .map-wrap { flex: 1; display: flex; flex-direction: column; position: relative; }
        #map { flex: 1; width: 100%; height: 100%; }
        #map.insert-mode { cursor: crosshair !important; }

        /* ── MODAL ── */
        .overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.6);
            z-index: 1000;
            backdrop-filter: blur(4px);
            align-items: center; justify-content: center;
        }
        .overlay.active { display: flex; }

        .modal {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 12px;
            width: 420px;
            max-width: calc(100vw - 24px);
            box-shadow: var(--shadow);
            animation: slideUp .2s ease;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .modal-head {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 20px 13px;
            border-bottom: 1px solid var(--border);
        }

        .modal-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
        }

        .modal-close {
            background: none; border: none;
            color: var(--muted);
            cursor: pointer; padding: 4px;
            border-radius: 5px;
            transition: color .13s;
            line-height: 0;
        }
        .modal-close:hover { color: var(--text); }

        .modal-body { padding: 16px 20px; }

        .coord-row-modal {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: 6px; margin-bottom: 10px;
        }

        .drag-hint {
            font-size: 11.5px; color: var(--muted);
            padding: 6px 10px; background: var(--bg);
            border: 1px dashed var(--border); border-radius: 6px;
            margin-bottom: 10px; text-align: center;
        }

        .modal-foot {
            display: flex; gap: 8px;
            padding: 12px 20px 16px;
            border-top: 1px solid var(--border);
        }

        .btn {
            flex: 1; display: inline-flex; align-items: center;
            justify-content: center; gap: 6px; padding: 9px;
            border-radius: var(--radius);
            font-family: var(--font);
            font-size: 13px; font-weight: 600;
            cursor: pointer; border: none; transition: opacity .15s;
        }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-primary:hover { opacity: .88; }
        .btn-primary:disabled { opacity: .45; cursor: not-allowed; }
        .btn-ghost {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text2);
        }
        .btn-ghost:hover { border-color: var(--text2); color: var(--text); }

        /* ── CONFIRM ── */
        .confirm-modal {
            background: var(--panel); border: 1px solid var(--border);
            border-radius: 12px; width: 310px;
            max-width: calc(100vw - 24px);
            padding: 24px 20px 18px; text-align: center;
            box-shadow: var(--shadow); animation: slideUp .2s ease;
        }
        .confirm-icon { margin-bottom: 10px; line-height: 0; }
        .confirm-title { font-size: 14px; font-weight: 600; margin-bottom: 5px; }
        .confirm-msg { font-size: 12.5px; color: var(--muted); margin-bottom: 18px; line-height: 1.5; }
        .confirm-btns { display: flex; gap: 8px; }

        /* ── TOAST ── */
        .toast {
            position: fixed; bottom: 20px; right: 16px; z-index: 2000;
            background: var(--panel2);
            border: 1px solid var(--border);
            border-radius: 8px; padding: 10px 14px;
            display: flex; align-items: center; gap: 9px;
            font-size: 13px; box-shadow: var(--shadow);
            transform: translateX(120%);
            transition: transform .28s cubic-bezier(.34,1.56,.64,1);
            min-width: 210px;
        }
        .toast.show { transform: translateX(0); }
        .toast-dot {
            width: 7px; height: 7px;
            border-radius: 50%; flex-shrink: 0;
        }
        .toast.success .toast-dot { background: var(--green); }
        .toast.error   .toast-dot { background: var(--red); }

        /* Leaflet popup */
        .leaflet-popup-content-wrapper {
            background: var(--panel) !important;
            border: 1px solid var(--border) !important;
            border-radius: 8px !important;
            color: var(--text) !important;
            box-shadow: var(--shadow) !important;
        }
        .leaflet-popup-tip { background: var(--panel) !important; }
        .leaflet-popup-content { margin: 12px 14px !important; }
    </style>
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
    <div class="brand">
        <div class="brand-icon">⛽</div>
        <div>
            <div class="brand-text">WebGIS SPBU</div>
            <div class="brand-sub">Manajemen Lokasi SPBU</div>
        </div>
    </div>
    <div class="legend">
        <div class="legend-item">
            <div class="legend-dot" style="background:var(--green)"></div>
            <span>Buka 24 Jam</span>
        </div>
        <div class="legend-item">
            <div class="legend-dot" style="background:var(--red)"></div>
            <span>Tidak 24 Jam</span>
        </div>
    </div>
</div>

<!-- LAYOUT -->
<div class="layout">

    <!-- SIDEBAR -->
    <div class="sidebar">

        <!-- TAB BAR -->
        <div class="tab-bar">
            <button class="tab-btn active" id="tabData" onclick="switchTab('data')">Data SPBU</button>
            <button class="tab-btn" id="tabTambah" onclick="switchTab('tambah')">Tambah SPBU</button>
        </div>

        <!-- PANEL: DATA SPBU -->
        <div class="tab-panel active" id="panelData">

            <!-- Stats -->
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

            <!-- Search -->
            <div class="search-wrap">
                <div class="search-box">
                    <span class="search-icon">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                    </span>
                    <input type="text" id="searchInput" placeholder="Cari nama atau nomor SPBU...">
                </div>
            </div>

            <!-- List -->
            <div class="data-list" id="dataList">
                <div class="empty-state">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <p>Memuat data...</p>
                </div>
            </div>
        </div>

        <!-- PANEL: TAMBAH SPBU -->
        <div class="tab-panel" id="panelTambah">
            <div class="form-header">
                <h2>Tambah SPBU Baru</h2>
                <p>Klik pada peta untuk menentukan lokasi, lalu isi data di bawah.</p>
            </div>

            <div class="form-body">
                <div class="coord-row">
                    <div class="coord-chip">
                        <label>Latitude</label>
                        <span id="insertLat">—</span>
                    </div>
                    <div class="coord-chip">
                        <label>Longitude</label>
                        <span id="insertLng">—</span>
                    </div>
                </div>

                <div class="click-hint" id="clickHint">
                    Klik pada peta untuk meletakkan titik SPBU
                </div>

                <div class="divider-line"></div>

                <div class="form-group">
                    <label>Nama SPBU</label>
                    <input type="text" id="iNama" placeholder="Contoh: SPBU Pontianak Kota" autocomplete="off">
                </div>

                <div class="form-group">
                    <label>Nomor SPBU</label>
                    <input type="text" id="iNomor" placeholder="Contoh: 64.761.01" autocomplete="off">
                </div>

                <div class="form-group">
                    <label>Status Operasional</label>
                    <select id="iStatus">
                        <option value="">Pilih status...</option>
                        <option value="buka 24 jam">Buka 24 Jam</option>
                        <option value="tidak">Tidak 24 Jam</option>
                    </select>
                </div>

                <div class="divider-line"></div>

                <button class="btn-simpan" id="btnSimpan" onclick="simpanData()">
                    Simpan Data SPBU
                </button>
                <button class="btn-reset" onclick="resetForm()">Reset</button>
            </div>
        </div>

    </div>

    <!-- MAP -->
    <div class="map-wrap">
        <div id="map"></div>
        <div class="insert-mode-badge" id="insertBadge">
            Mode input aktif — klik peta untuk memilih lokasi
        </div>
    </div>

</div>

<!-- MODAL EDIT -->
<div class="overlay" id="overlay">
    <div class="modal">
        <div class="modal-head">
            <div class="modal-title">Edit Data SPBU</div>
            <button class="modal-close" onclick="closeModal()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <div class="coord-row-modal">
                <div class="coord-chip">
                    <label>Latitude</label>
                    <span id="dispLat">—</span>
                </div>
                <div class="coord-chip">
                    <label>Longitude</label>
                    <span id="dispLng">—</span>
                </div>
            </div>
            <div class="drag-hint">Geser marker di peta untuk mengubah koordinat</div>
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
                    <option value="">Pilih status...</option>
                    <option value="buka 24 jam">Buka 24 Jam</option>
                    <option value="tidak">Tidak 24 Jam</option>
                </select>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-ghost" onclick="closeModal()">Batal</button>
            <button class="btn btn-primary" id="btnUpdate" onclick="updateData()">Simpan Perubahan</button>
        </div>
    </div>
</div>

<!-- CONFIRM DELETE -->
<div class="overlay" id="overlayConfirm">
    <div class="confirm-modal">
        <div class="confirm-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="1.6" stroke-linecap="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
        </div>
        <div class="confirm-title">Hapus Data SPBU?</div>
        <div class="confirm-msg" id="confirmMsg">Data ini akan dihapus permanen dan tidak dapat dikembalikan.</div>
        <div class="confirm-btns">
            <button class="btn btn-ghost" style="flex:1" onclick="closeConfirm()">Batal</button>
            <button class="btn btn-primary" style="flex:1;background:var(--red)" onclick="confirmDelete()">Hapus</button>
        </div>
    </div>
</div>

<!-- TOAST -->
<div class="toast" id="toast">
    <span class="toast-dot"></span>
    <span id="toastMsg"></span>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// ═══════════════════════════════════════════
// MAP INIT
// ═══════════════════════════════════════════
const map = L.map('map', {
    center: [-0.0263, 109.3425],
    zoom: 13,
    zoomControl: false
});
L.control.zoom({ position: 'bottomright' }).addTo(map);

const layerOSM = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 19
});
const layerSat = L.tileLayer(
    'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
    { attribution: '© Esri', maxZoom: 19 }
);
layerOSM.addTo(map);

// Layer groups untuk filter overlay
const lg24    = L.layerGroup().addTo(map);
const lgTidak = L.layerGroup().addTo(map);

L.control.layers(
    { 'Street Map': layerOSM, 'Satelit': layerSat },
    { '⛽ Buka 24 Jam': lg24, '⛽ Tidak 24 Jam': lgTidak },
    { position: 'topright', collapsed: true }
).addTo(map);
setTimeout(() => map.invalidateSize(), 200);

// ═══════════════════════════════════════════
// STATE
// ═══════════════════════════════════════════
let allData    = [];
let markers    = {};
let editId     = null;
let deleteId   = null;
let activeId   = null;
let editLat    = null;
let editLng    = null;

// Insert state
let insertMode  = false;
let tempMarker  = null;
let tempLat     = null;
let tempLng     = null;

// ═══════════════════════════════════════════
// TAB SWITCHER
// ═══════════════════════════════════════════
function switchTab(tab) {
    const isInsert = tab === 'tambah';

    document.getElementById('tabData').classList.toggle('active', !isInsert);
    document.getElementById('tabTambah').classList.toggle('active', isInsert);
    document.getElementById('panelData').classList.toggle('active', !isInsert);
    document.getElementById('panelTambah').classList.toggle('active', isInsert);

    // Toggle insert mode on map
    insertMode = isInsert;
    const mapEl = document.getElementById('map');
    const badge = document.getElementById('insertBadge');
    if (isInsert) {
        mapEl.classList.add('insert-mode');
        badge.classList.add('visible');
    } else {
        mapEl.classList.remove('insert-mode');
        badge.classList.remove('visible');
        // Remove temp marker when switching away
        if (tempMarker) { map.removeLayer(tempMarker); tempMarker = null; }
        tempLat = null; tempLng = null;
    }
    setTimeout(() => map.invalidateSize(), 10);
}

// ═══════════════════════════════════════════
// ICON FACTORIES
// ═══════════════════════════════════════════
function makeIcon(status, highlighted = false) {
    const color = status === 'buka 24 jam' ? '#22c55e' : '#ef4444';
    const glow  = highlighted
        ? `box-shadow:0 0 0 6px rgba(247,201,72,.45),0 4px 16px ${color}88;`
        : `box-shadow:0 4px 12px ${color}55;`;
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

function makeTempIcon() {
    return L.divIcon({
        className: '',
        html: `<div style="
            width:38px;height:38px;
            background:#f7c948;
            border:3px solid #fff;
            border-radius:50% 50% 50% 0;
            transform:rotate(-45deg);
            box-shadow:0 0 0 5px rgba(247,201,72,.35),0 4px 18px rgba(247,201,72,.6);
            display:flex;align-items:center;justify-content:center;
        "><span style="transform:rotate(45deg);font-size:16px">➕</span></div>`,
        iconSize: [38, 44],
        iconAnchor: [19, 44]
    });
}

// ═══════════════════════════════════════════
// POPUP HTML
// ═══════════════════════════════════════════
function popupHtml(row) {
    const is24   = row.status === 'buka 24 jam';
    const sColor = is24 ? '#22c55e' : '#ef4444';
    return `
        <div style="font-family:'Plus Jakarta Sans',sans-serif;min-width:190px">
            <div style="font-size:14.5px;font-weight:700;margin-bottom:4px">⛽ ${esc(row.nama)}</div>
            <div style="font-family:monospace;font-size:11px;color:#6a8aa5;margin-bottom:6px">${esc(row.nomor_spbu)}</div>
            <div style="display:inline-block;padding:2px 10px;border-radius:99px;font-size:11px;font-weight:600;
                background:${sColor}22;color:${sColor};border:1px solid ${sColor}44;margin-bottom:8px">
                ${is24 ? '✅ Buka 24 Jam' : '🔴 Tidak 24 Jam'}
            </div>
            <hr style="border-color:#1e3347;margin:6px 0">
            <div style="font-family:monospace;font-size:10px;color:#3a5a70">
                ${parseFloat(row.latitude).toFixed(6)}, ${parseFloat(row.longitude).toFixed(6)}
            </div>
            <div style="display:flex;gap:6px;margin-top:10px">
                <button onclick="openEdit(${row.id})" style="
                    flex:1;padding:5px;border-radius:6px;border:1px solid #1e3347;
                    background:transparent;color:#f7c948;font-size:11px;font-weight:600;cursor:pointer">
                    ✏️ Edit
                </button>
                <button onclick="openDelete(${row.id},'${esc(row.nama)}')" style="
                    flex:1;padding:5px;border-radius:6px;border:1px solid #1e3347;
                    background:transparent;color:#ef4444;font-size:11px;font-weight:600;cursor:pointer">
                    🗑️ Hapus
                </button>
            </div>
        </div>`;
}

// ═══════════════════════════════════════════
// ADD MARKER (existing data)
// ═══════════════════════════════════════════
function addMarker(row) {
    const is24 = row.status === 'buka 24 jam';
    const targetGroup = is24 ? lg24 : lgTidak;

    const m = L.marker([row.latitude, row.longitude], {
        icon: makeIcon(row.status),
        draggable: true
    }).addTo(targetGroup).bindPopup(popupHtml(row));

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

    m.on('click', function() {
        if (insertMode) return;
        activeId = row.id;
        renderList(getFilteredList());
        const el = document.querySelector(`[data-id="${row.id}"]`);
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    markers[row.id] = m;
}

// ═══════════════════════════════════════════
// LAYER OVERLAY EVENTS — sync sidebar with layer control
// ═══════════════════════════════════════════
map.on('overlayadd overlayremove', function() {
    renderList(getFilteredList());
});

function getFilteredList() {
    const show24    = map.hasLayer(lg24);
    const showTidak = map.hasLayer(lgTidak);
    const q         = document.getElementById('searchInput').value.toLowerCase();
    let   data      = allData;
    if (show24 && !showTidak)  data = allData.filter(r => r.status === 'buka 24 jam');
    if (!show24 && showTidak)  data = allData.filter(r => r.status !== 'buka 24 jam');
    if (!show24 && !showTidak) data = [];
    if (q) data = data.filter(r =>
        r.nama.toLowerCase().includes(q) || r.nomor_spbu.toLowerCase().includes(q)
    );
    return data;
}

// ═══════════════════════════════════════════
// MAP CLICK — insert mode
// ═══════════════════════════════════════════
map.on('click', function(e) {
    if (!insertMode) return;

    tempLat = e.latlng.lat;
    tempLng = e.latlng.lng;

    if (tempMarker) map.removeLayer(tempMarker);

    tempMarker = L.marker([tempLat, tempLng], {
        icon: makeTempIcon(),
        draggable: true
    }).addTo(map);

    tempMarker.on('dragend', function() {
        const p = tempMarker.getLatLng();
        tempLat = p.lat;
        tempLng = p.lng;
        updateInsertCoordDisplay();
    });

    updateInsertCoordDisplay();

    const hint = document.getElementById('clickHint');
    hint.textContent = 'Lokasi dipilih — geser marker untuk koreksi koordinat';
    hint.classList.add('ready');
});

function updateInsertCoordDisplay() {
    document.getElementById('insertLat').textContent = tempLat.toFixed(7);
    document.getElementById('insertLng').textContent = tempLng.toFixed(7);
}

// ═══════════════════════════════════════════
// RENDER LIST
// ═══════════════════════════════════════════
function renderList(data) {
    const el = document.getElementById('dataList');
    if (!data.length) {
        el.innerHTML = `<div class="empty-state">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <p>Tidak ada data SPBU</p>
        </div>`;
        return;
    }
    el.innerHTML = data.map(row => {
        const is24  = row.status === 'buka 24 jam';
        const color = is24 ? 'var(--green)' : 'var(--red)';
        return `
        <div class="data-item ${activeId == row.id ? 'active' : ''}"
             data-id="${row.id}" onclick="focusMarker(${row.id})">
            <div class="item-dot" style="background:${color}"></div>
            <div class="item-info">
                <div class="item-name">${esc(row.nama)}</div>
                <div class="item-meta">
                    <span class="item-nomor">${esc(row.nomor_spbu)}</span>
                    <span class="item-badge ${is24 ? 'open' : 'close'}">${is24 ? '24 Jam' : 'Tidak'}</span>
                </div>
            </div>
            <div class="item-actions">
                <button class="icon-btn edit" title="Edit"
                    onclick="event.stopPropagation();openEdit(${row.id})">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                </button>
                <button class="icon-btn del" title="Hapus"
                    onclick="event.stopPropagation();openDelete(${row.id},'${esc(row.nama)}')">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                </button>
            </div>
        </div>`;
    }).join('');
}

// ═══════════════════════════════════════════
// FOCUS MARKER (from list click)
// ═══════════════════════════════════════════
function focusMarker(id) {
    activeId = id;
    renderList(getCurrentList());
    const row = allData.find(r => r.id == id);
    if (!row) return;
    map.flyTo([row.latitude, row.longitude], 16, { duration: 1 });
    setTimeout(() => { if (markers[id]) markers[id].openPopup(); }, 900);
}

// ═══════════════════════════════════════════
// LOAD DATA
// ═══════════════════════════════════════════
async function loadData() {
    try {
        const res  = await fetch('api.php?action=all');
        const data = await res.json();
        if (!data.success) throw new Error(data.message);
        allData = data.data;
        updateStats();

        // Clear existing markers from layer groups
        lg24.clearLayers();
        lgTidak.clearLayers();
        markers = {};

        allData.forEach(addMarker);

        // Render the sidebar list based on visible layers
        renderList(getFilteredList());

        if (allData.length) {
            const show24 = map.hasLayer(lg24);
            const showTidak = map.hasLayer(lgTidak);
            const visibleData = allData.filter(r => {
                const is24 = r.status === 'buka 24 jam';
                return (is24 && show24) || (!is24 && showTidak);
            });
            if (visibleData.length) {
                const bounds = L.latLngBounds(visibleData.map(r => [r.latitude, r.longitude]));
                map.fitBounds(bounds.pad(0.15));
            }
        }
    } catch(e) {
        document.getElementById('dataList').innerHTML =
            `<div class="empty-state"><div class="big">⚠️</div><p>${e.message}</p></div>`;
    }
}

function updateStats() {
    const total = allData.length;
    const jam24 = allData.filter(r => r.status === 'buka 24 jam').length;
    document.getElementById('statTotal').textContent = total;
    document.getElementById('stat24').textContent    = jam24;
    document.getElementById('statTidak').textContent = total - jam24;
}

function getCurrentList() {
    return getFilteredList();
}

document.getElementById('searchInput').addEventListener('input', () => renderList(getFilteredList()));

// ═══════════════════════════════════════════
// SIMPAN (INSERT)
// ═══════════════════════════════════════════
async function simpanData() {
    const nama   = document.getElementById('iNama').value.trim();
    const nomor  = document.getElementById('iNomor').value.trim();
    const status = document.getElementById('iStatus').value;

    if (tempLat === null) { showToast('Klik peta untuk pilih lokasi!', 'error'); return; }
    if (!nama)            { showToast('Nama SPBU wajib diisi!', 'error'); return; }
    if (!nomor)           { showToast('Nomor SPBU wajib diisi!', 'error'); return; }
    if (!status)          { showToast('Status wajib dipilih!', 'error'); return; }

    const btn = document.getElementById('btnSimpan');
    btn.disabled = true;
    btn.innerHTML = '⏳ Menyimpan...';

    const fd = new FormData();
    fd.append('action',     'save');
    fd.append('nama',       nama);
    fd.append('nomor_spbu', nomor);
    fd.append('status',     status);
    fd.append('latitude',   tempLat);
    fd.append('longitude',  tempLng);

    try {
        const res  = await fetch('api.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showToast('SPBU berhasil disimpan!', 'success');

            // Remove temp marker, reload data (will create permanent marker)
            if (tempMarker) { map.removeLayer(tempMarker); tempMarker = null; }
            resetForm();
            await loadData();

            // Switch back to data tab to see the new entry
            switchTab('data');
            // Highlight new marker after a beat
            if (data.id) {
                setTimeout(() => {
                    if (markers[data.id]) {
                        map.flyTo([tempLat, tempLng], 16, { duration: 1 });
                        setTimeout(() => markers[data.id] && markers[data.id].openPopup(), 900);
                    }
                }, 400);
            }
        } else {
            showToast('Gagal: ' + data.message, 'error');
        }
    } catch(e) {
        showToast('Error: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '💾 Simpan Data SPBU';
    }
}

// ═══════════════════════════════════════════
// RESET FORM
// ═══════════════════════════════════════════
function resetForm() {
    if (tempMarker) { map.removeLayer(tempMarker); tempMarker = null; }
    tempLat = null; tempLng = null;
    document.getElementById('insertLat').textContent = '— klik peta —';
    document.getElementById('insertLng').textContent = '— klik peta —';
    document.getElementById('iNama').value   = '';
    document.getElementById('iNomor').value  = '';
    document.getElementById('iStatus').value = '';
    const hint = document.getElementById('clickHint');
    hint.textContent = '⛽ Klik pada peta untuk meletakkan titik SPBU';
    hint.classList.remove('ready');
}

// ═══════════════════════════════════════════
// OPEN EDIT
// ═══════════════════════════════════════════
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
        markers[id].setIcon(makeIcon(row.status, true));
        markers[id].dragging.enable();
        markers[id].off('dragend');
        markers[id].on('dragend', function() {
            const p = markers[id].getLatLng();
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
            markers[editId].setIcon(makeIcon(row.status, false));
            markers[editId].setLatLng([row.latitude, row.longitude]);
        }
    }
    editId = null;
}

// ═══════════════════════════════════════════
// UPDATE
// ═══════════════════════════════════════════
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
            await loadData();
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

// ═══════════════════════════════════════════
// DELETE
// ═══════════════════════════════════════════
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

// ═══════════════════════════════════════════
// TOAST
// ═══════════════════════════════════════════
let toastTimer = null;
function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    document.getElementById('toastMsg').textContent = msg;
    t.className = `toast ${type} show`;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove('show'), 3000);
}

// ═══════════════════════════════════════════
// ESCAPE HTML
// ═══════════════════════════════════════════
function esc(str) {
    return String(str || '')
        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
        .replace(/>/g,'&gt;').replace(/"/g,'&quot;')
        .replace(/'/g,'&#39;');
}

// ═══════════════════════════════════════════
// INIT
// ═══════════════════════════════════════════
loadData();
</script>
</body>
</html>
