<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GeoTagr Pro — Photo Geotagging Tool</title>
  <link rel="stylesheet" href="assets/style.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="anonymous">
  <style>
    .leaflet-container { background: #dde8d8; }
    .leaflet-control-zoom a { background: #fff !important; color: #f97316 !important; border-color: #fed7aa !important; font-weight:700; }
    .leaflet-control-zoom a:hover { background: #fff7ed !important; }
    .leaflet-control-attribution { background: rgba(255,255,255,0.85) !important; color: #9ca3af !important; font-size:10px; }
    .leaflet-control-attribution a { color: #f97316 !important; }
  </style>
</head>
<body>

<header class="header">
  <a href="/" class="logo">
    <div class="logo-icon">📍</div>
    GeoTagr <span>Pro</span>
  </a>
  <span class="header-badge">Free · No Sign-In · Private</span>
</header>

<div class="app-container">

  <!-- LEFT: Controls -->
  <aside class="controls-panel">

    <!-- Upload -->
    <div class="panel-section">
      <div class="section-title">Upload Photos</div>
      <div class="upload-zone" id="upload-zone">
        <input type="file" id="file-input" accept=".jpg,.jpeg,.png,.webp,.heic,.heif" multiple>
        <span class="upload-icon">🖼</span>
        <h3>Drop photos here or click to browse</h3>
        <p>JPG · PNG · WebP · HEIC &nbsp;·&nbsp; Up to 15 photos, 50 MB each</p>
      </div>
      <div id="preview-area" class="hidden">
        <div id="preview-grid" class="preview-grid"></div>
        <div id="existing-gps" class="existing-gps hidden">
          <span>📍</span>
          <div>
            <strong>GPS found in photo</strong>
            <span id="gps-val"></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Batch list -->
    <div class="panel-section hidden" id="batch-section">
      <div class="section-title">Files <span class="file-count" id="file-count"></span></div>
      <div id="batch-list" class="batch-list"></div>
    </div>

    <!-- Location -->
    <div class="panel-section" id="coords-section">
      <div class="section-title">Location</div>
      <div class="coord-hint">📌 Click the map to set coordinates, or type below</div>
      <div class="form-row">
        <div class="form-group">
          <label>Latitude</label>
          <input type="text" id="coord-lat-disp" placeholder="e.g. 48.8566">
        </div>
        <div class="form-group">
          <label>Longitude</label>
          <input type="text" id="coord-lon-disp" placeholder="e.g. 2.3522">
        </div>
      </div>
      <div class="form-group">
        <label>Altitude (meters)</label>
        <input type="number" id="input-alt" placeholder="0" step="0.1" min="-500" max="9000">
      </div>
    </div>

    <!-- SEO / Image Metadata -->
    <div class="panel-section">
      <div class="section-title">Image SEO Fields</div>
      <div class="form-group">
        <label>Description / Caption</label>
        <textarea id="input-description" placeholder="Describe the photo for SEO and search engines…"></textarea>
      </div>
      <div class="form-group">
        <label>Keywords / Tags</label>
        <input type="text" id="input-keywords" placeholder="travel, landscape, city (comma-separated)">
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label>Copyright</label>
        <input type="text" id="input-copyright" placeholder="© 2025 Your Name">
      </div>
    </div>

    <!-- Privacy -->
    <div class="panel-section">
      <div class="section-title">Privacy</div>
      <div class="toggle-row">
        <div>
          <span class="toggle-label">Remove All EXIF Data</span>
          <span class="toggle-sub">Strips all metadata before download</span>
        </div>
        <label class="switch">
          <input type="checkbox" id="strip-toggle" onchange="onStripToggle()">
          <span class="switch-slider"></span>
        </label>
      </div>
    </div>

    <!-- Download -->
    <div class="download-area">
      <button type="button" class="btn-download" id="btn-download" disabled onclick="submitForm()">
        ⬇ Download Geotagged Photo
      </button>
      <p class="download-note">Files are deleted from server immediately after download</p>
    </div>

  </aside>

  <!-- RIGHT: Map -->
  <section class="map-panel">
    <div class="map-search-bar">
      <input type="text" id="search-input" placeholder="Search city, address, or landmark…" autocomplete="off">
      <button class="btn-locate" id="btn-geolocate" title="Use my location">📍</button>
    </div>
    <div id="search-results" class="search-results"></div>
    <div id="map"></div>
    <div class="layer-toggle">
      <button class="layer-btn active" id="btn-street">🗺 Street</button>
      <button class="layer-btn" id="btn-satellite">🛰 Satellite</button>
    </div>
    <div class="map-bottom">
      <div class="coords-bar">
        <div class="coord-pill"><span>LAT</span><input type="text" id="input-lat" placeholder="–"></div>
        <div class="coord-pill"><span>LON</span><input type="text" id="input-lon" placeholder="–"></div>
        <button class="dms-btn" id="dms-toggle">DMS</button>
      </div>
      <div id="reverse-addr" class="reverse-addr">Click the map to drop a pin and set GPS coordinates</div>
    </div>
  </section>

</div>

<div id="toast-container" class="toast-container"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/exifr@7.1.3/dist/lite.umd.js"></script>
<script src="assets/app.js"></script>

<script>
function onStripToggle() {
  const on = document.getElementById('strip-toggle').checked;
  const sec = document.getElementById('coords-section');
  sec.style.opacity = on ? '0.35' : '1';
  sec.style.pointerEvents = on ? 'none' : 'auto';
  if (typeof updateDownloadButton === 'function') updateDownloadButton();
}
</script>
</body>
</html>
