<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="GeoTagr Pro — Embed GPS coordinates into your photos. Free, private, no sign-in required.">
  <title>GeoTagr Pro — Photo Geotagging Tool</title>

  <link rel="stylesheet" href="assets/style.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous">

  <style>
    .leaflet-container { background: #e8ebe8; }
    .leaflet-control-zoom a {
      background: rgba(255,255,255,0.97) !important;
      color: #f97316 !important;
      border-color: #fed7aa !important;
      font-weight: 700;
    }
    .leaflet-control-zoom a:hover { background: #ffedd5 !important; }
    .leaflet-control-attribution {
      background: rgba(255,255,255,0.85) !important;
      color: #9ca3af !important;
      font-size: 10px;
    }
    .leaflet-control-attribution a { color: #f97316 !important; }
  </style>
</head>
<body>

<!-- ── Header ────────────────────────────────────────────────── -->
<header class="header">
  <a href="/" class="logo">
    <div class="logo-icon">📍</div>
    GeoTagr <span>Pro</span>
  </a>
  <div class="header-right">
    <span class="header-tag">v2.0</span>
    <span class="header-badge">Free · No Sign-In · Private</span>
  </div>
</header>

<!-- ── App ───────────────────────────────────────────────────── -->
<div class="app-container">

  <!-- ══ LEFT: Controls ════════════════════════════════════════ -->
  <aside class="controls-panel">
    <form id="geotag-form">

      <!-- Upload Section -->
      <div class="panel-section">
        <div class="section-title">Upload Photos</div>

        <!-- Single / Bulk toggle -->
        <div class="mode-toggle">
          <button type="button" class="mode-btn active" id="mode-single" onclick="setMode('single')">
            🖼 Single Photo
          </button>
          <button type="button" class="mode-btn" id="mode-bulk" onclick="setMode('bulk')">
            📦 Bulk Upload
            <span style="background:var(--orange-light);color:var(--orange-dark);font-size:0.65rem;padding:1px 5px;border-radius:8px;font-weight:800;">up to 15</span>
          </button>
        </div>

        <!-- Drop Zone -->
        <div class="upload-zone" id="upload-zone">
          <input type="file" id="file-input"
                 accept=".jpg,.jpeg,.png,.webp,.heic,.heif"
                 style="z-index:2;">
          <span class="upload-icon">🖼</span>
          <h3 id="upload-title">Drop a photo here or click to browse</h3>
          <p id="upload-sub">JPG · PNG · WebP · HEIC · up to 50 MB</p>
          <span class="limit-badge" id="upload-limit">Single file mode</span>
        </div>

        <!-- Preview + GPS badge -->
        <div id="preview-area" class="hidden">
          <div id="preview-grid" class="preview-grid"></div>
          <div id="existing-gps" class="existing-gps">
            <span class="gps-icon">📍</span>
            <div>
              <span class="gps-label">GPS coordinates found in photo</span>
              <span class="gps-values" id="gps-val"></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Batch file list (bulk mode) -->
      <div class="panel-section hidden" id="batch-section">
        <div class="section-title">Files <span style="font-size:0.68rem;font-weight:400;color:var(--muted);text-transform:none;letter-spacing:0;" id="file-count"></span></div>
        <div id="batch-list" class="batch-list"></div>
      </div>

      <!-- Location Section -->
      <div class="panel-section" id="coords-section">
        <div class="section-title">Location</div>

        <div class="coord-inputs">
          <div class="coord-hint">
            <span>📌</span> Click the map → or type coordinates below
          </div>
          <div class="form-row">
            <div class="form-group" style="margin-bottom:0">
              <label>Latitude</label>
              <input type="text" id="coord-lat-disp" placeholder="e.g. 48.8566"
                     oninput="syncLatFromDisp(this.value)"
                     onchange="triggerCoordSync()">
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label>Longitude</label>
              <input type="text" id="coord-lon-disp" placeholder="e.g. 2.3522"
                     oninput="syncLonFromDisp(this.value)"
                     onchange="triggerCoordSync()">
            </div>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Altitude (m)</label>
            <input type="number" id="input-alt" placeholder="0" step="0.1" min="-500" max="9000">
          </div>
          <div class="form-group">
            <label>Date / Time Taken</label>
            <input type="datetime-local" id="input-datetime">
          </div>
        </div>
      </div>

      <!-- EXIF Write Options -->
      <div class="panel-section">
        <div class="section-title">Write EXIF Options</div>

        <div class="exif-options">

          <!-- GPS Card -->
          <div class="exif-card active open" id="card-gps">
            <div class="exif-card-header" onclick="toggleCard('card-gps')">
              <div class="exif-card-title">
                <div class="card-icon">🛰</div>
                <div>
                  GPS Coordinates
                  <div class="exif-card-subtitle">Latitude, longitude, altitude</div>
                </div>
              </div>
              <input type="checkbox" id="write-gps" checked onclick="event.stopPropagation()" style="width:16px;height:16px;accent-color:var(--orange);cursor:pointer;">
            </div>
            <div class="exif-card-body" id="body-gps" style="display:block;">
              <p class="text-sm text-muted" style="margin-top:0;">Set coordinates using the map on the right or type them in the Location field above.</p>
            </div>
          </div>

          <!-- Metadata Card -->
          <div class="exif-card" id="card-meta">
            <div class="exif-card-header" onclick="toggleCard('card-meta')">
              <div class="exif-card-title">
                <div class="card-icon">📝</div>
                <div>
                  Description & Tags
                  <div class="exif-card-subtitle">Caption, keywords, copyright</div>
                </div>
              </div>
              <input type="checkbox" id="write-meta" onclick="event.stopPropagation()" style="width:16px;height:16px;accent-color:var(--orange);cursor:pointer;">
            </div>
            <div class="exif-card-body" id="body-meta">
              <div class="form-group">
                <label>Description / Caption</label>
                <textarea id="input-description" placeholder="Describe this photo…"></textarea>
              </div>
              <div class="form-group">
                <label>Keywords / Tags</label>
                <input type="text" id="input-keywords" placeholder="travel, landscape, city (comma-separated)">
              </div>
              <div class="form-group" style="margin-bottom:0;">
                <label>Copyright</label>
                <input type="text" id="input-copyright" placeholder="© 2025 Your Name">
              </div>
            </div>
          </div>

          <!-- Strip EXIF Card -->
          <div class="exif-card" id="card-strip">
            <div class="exif-card-header" onclick="toggleCard('card-strip')">
              <div class="exif-card-title">
                <div class="card-icon">🔒</div>
                <div>
                  Privacy Mode
                  <div class="exif-card-subtitle">Remove ALL metadata before download</div>
                </div>
              </div>
              <label class="switch" onclick="event.stopPropagation()">
                <input type="checkbox" id="strip-toggle" onchange="onStripToggle()">
                <span class="switch-slider"></span>
              </label>
            </div>
            <div class="exif-card-body" id="body-strip">
              <div class="strip-banner visible">
                <span class="strip-icon">⚠️</span>
                <div>
                  <strong>All EXIF data will be removed</strong>
                  <span>GPS, camera info, timestamps — everything stripped for privacy.</span>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>

    </form>

    <!-- Sticky Download Bar -->
    <div class="download-area">
      <button type="button" class="btn btn-primary" id="btn-download" disabled onclick="submitForm()">
        ⬇ Download Geotagged Photo
      </button>
      <p class="text-sm text-muted mt-2" style="text-align:center;">
        Files processed on server &amp; deleted immediately after download
      </p>
    </div>

  </aside>

  <!-- ══ RIGHT: Map ══════════════════════════════════════════ -->
  <section class="map-panel">

    <!-- Search -->
    <div class="map-search-bar">
      <input type="text" id="search-input"
             placeholder="Search city, address, or landmark…"
             autocomplete="off">
      <button class="btn-icon" id="btn-geolocate" title="Use my location">📍</button>
    </div>

    <!-- Search Results -->
    <div id="search-results" class="search-results"></div>

    <!-- Map -->
    <div id="map"></div>

    <!-- Layer Toggle -->
    <div class="layer-toggle">
      <button class="layer-btn active" id="btn-street">🗺 Street</button>
      <button class="layer-btn" id="btn-satellite">🛰 Satellite</button>
    </div>

    <!-- Coords Bar + Address -->
    <div class="map-overlay-bottom">
      <div class="coords-bar">
        <div class="coord-group">
          <label>LAT</label>
          <input type="text" id="input-lat" placeholder="–" autocomplete="off">
        </div>
        <div class="coord-group">
          <label>LON</label>
          <input type="text" id="input-lon" placeholder="–" autocomplete="off">
        </div>
        <button class="dms-toggle" id="dms-toggle">DMS</button>
      </div>
      <div id="reverse-addr" class="reverse-geocode-addr">
        Click the map to drop a pin and set GPS coordinates
      </div>
    </div>

  </section>

</div><!-- /app-container -->

<!-- Toasts -->
<div id="toast-container" class="toast-container"></div>

<!-- Leaflet -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV/XN/WPQ="
        crossorigin="anonymous"></script>

<!-- EXIF reader -->
<script src="https://cdn.jsdelivr.net/npm/exifr@7.1.3/dist/lite.umd.js"></script>

<!-- App -->
<script src="assets/app.js"></script>

<script>
/* ── Helpers exposed to inline handlers ─────────────────── */
function syncLatFromDisp(v) { document.getElementById('input-lat').value = v; }
function syncLonFromDisp(v) { document.getElementById('input-lon').value = v; }
function triggerCoordSync() {
  document.getElementById('input-lat').dispatchEvent(new Event('change'));
}

function setMode(mode) {
  const single = document.getElementById('mode-single');
  const bulk   = document.getElementById('mode-bulk');
  const inp    = document.getElementById('file-input');
  const title  = document.getElementById('upload-title');
  const sub    = document.getElementById('upload-sub');
  const limit  = document.getElementById('upload-limit');

  if (mode === 'single') {
    single.classList.add('active'); bulk.classList.remove('active');
    inp.removeAttribute('multiple');
    title.textContent = 'Drop a photo here or click to browse';
    limit.textContent = 'Single file mode';
    window._uploadMode = 'single';
  } else {
    bulk.classList.add('active'); single.classList.remove('active');
    inp.setAttribute('multiple', '');
    title.textContent = 'Drop up to 15 photos or click to browse';
    limit.textContent = 'Bulk mode — up to 15 files';
    window._uploadMode = 'bulk';
  }
}

function toggleCard(id) {
  const card = document.getElementById(id);
  const body = card.querySelector('.exif-card-body');
  const isOpen = card.classList.contains('open');
  card.classList.toggle('open', !isOpen);
  if (body) body.style.display = isOpen ? 'none' : 'block';
}

function onStripToggle() {
  const on  = document.getElementById('strip-toggle').checked;
  const sec = document.getElementById('coords-section');
  sec.style.opacity       = on ? '0.35' : '1';
  sec.style.pointerEvents = on ? 'none' : 'auto';
  if (typeof updateDownloadButton === 'function') updateDownloadButton();
}

window._uploadMode = 'single';
</script>

</body>
</html>
