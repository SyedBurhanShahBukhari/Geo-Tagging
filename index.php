<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="GeoTagr Pro — Embed GPS coordinates into your photos. Free, private, no sign-in required.">
  <title>GeoTagr Pro — Photo Geotagging Tool</title>

  <!-- Styles -->
  <link rel="stylesheet" href="assets/style.css">

  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="anonymous">

  <style>
    /* Inline critical overrides */
    .leaflet-container { background: #0f1628; }
    .leaflet-control-zoom a {
      background: rgba(10,15,30,0.92) !important;
      color: #00d4aa !important;
      border-color: #1e2d50 !important;
      backdrop-filter: blur(8px);
    }
    .leaflet-control-zoom a:hover { background: rgba(0,212,170,0.15) !important; }
    .leaflet-control-attribution {
      background: rgba(10,15,30,0.7) !important;
      color: #6b7fa8 !important;
      font-size: 10px;
    }
    .leaflet-control-attribution a { color: #00d4aa !important; }
    #preview-area { margin-top: 0; }
    .layer-btn {
      background: rgba(10,15,30,0.92);
      border: 1px solid #1e2d50;
      color: #f0f4ff;
      padding: 6px 12px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 0.78rem;
      font-family: inherit;
      font-weight: 600;
      transition: all 0.2s;
      backdrop-filter: blur(8px);
    }
    .layer-btn.active, .layer-btn:hover {
      border-color: #00d4aa;
      color: #00d4aa;
      background: rgba(0,212,170,0.1);
    }
  </style>
</head>
<body>

<!-- ── Header ─────────────────────────────────────────────────────────────── -->
<header class="header">
  <a href="/" class="logo">
    <div class="logo-icon">📍</div>
    GeoTagr <span>Pro</span>
  </a>
  <div class="header-badge">Free · No Sign-In · Private</div>
</header>

<!-- ── App Container ──────────────────────────────────────────────────────── -->
<div class="app-container">

  <!-- ── Map Panel ──────────────────────────────────────────────────────── -->
  <section class="map-panel">
    <!-- Search Bar -->
    <div class="map-search-bar">
      <input type="text" id="search-input" placeholder="Search for a place, city, or address…" autocomplete="off">
      <button class="btn-icon" id="btn-geolocate" title="Use my location">📍</button>
    </div>

    <!-- Search Results Dropdown -->
    <div id="search-results" class="search-results"></div>

    <!-- Leaflet Map -->
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
          <label for="input-lat">LAT</label>
          <input type="text" id="input-lat" placeholder="–" autocomplete="off">
        </div>
        <div class="coord-group">
          <label for="input-lon">LON</label>
          <input type="text" id="input-lon" placeholder="–" autocomplete="off">
        </div>
        <button class="dms-toggle" id="dms-toggle" title="Toggle DMS / Decimal">DMS</button>
      </div>
      <div id="reverse-addr" class="reverse-geocode-addr">
        Click the map to drop a pin and set coordinates
      </div>
    </div>
  </section>

  <!-- ── Controls Panel ─────────────────────────────────────────────────── -->
  <aside class="controls-panel">
    <form id="geotag-form">

      <!-- Upload Section -->
      <div class="panel-section">
        <div class="section-title">Upload Photos</div>

        <div class="upload-zone" id="upload-zone">
          <input type="file" id="file-input" accept=".jpg,.jpeg,.png,.webp,.heic,.heif"
                 multiple style="z-index:2;">
          <span class="upload-icon">🖼</span>
          <h3>Drop photos here or click to browse</h3>
          <p>JPG · PNG · WebP · HEIC &nbsp;·&nbsp; Up to 10 files, 50 MB each</p>
        </div>

        <!-- Preview Grid -->
        <div id="preview-area" class="hidden">
          <div id="preview-grid" class="preview-grid"></div>

          <!-- Existing GPS display -->
          <div id="existing-gps" class="existing-gps">
            <div class="gps-label">Existing GPS Coordinates Found</div>
            <div class="gps-values"></div>
          </div>
        </div>
      </div>

      <!-- Coordinates Section -->
      <div class="panel-section" id="coords-section">
        <div class="section-title">Location</div>

        <p class="text-sm text-muted" style="margin-bottom:10px;">
          Click the map to set coordinates, or search for a location above.
        </p>

        <div class="form-row">
          <div class="form-group">
            <label for="coord-lat-disp">Latitude</label>
            <input type="text" id="coord-lat-disp" placeholder="e.g. 48.8566"
                   oninput="document.getElementById('input-lat').value=this.value"
                   onchange="document.getElementById('input-lat').dispatchEvent(new Event('change'))">
          </div>
          <div class="form-group">
            <label for="coord-lon-disp">Longitude</label>
            <input type="text" id="coord-lon-disp" placeholder="e.g. 2.3522"
                   oninput="document.getElementById('input-lon').value=this.value"
                   onchange="document.getElementById('input-lon').dispatchEvent(new Event('change'))">
          </div>
        </div>

        <div class="form-group">
          <label for="input-alt">Altitude (meters above sea level)</label>
          <input type="number" id="input-alt" placeholder="0" step="0.1" min="-500" max="9000">
        </div>
      </div>

      <!-- Metadata Section -->
      <div class="panel-section">
        <div class="section-title">Metadata</div>

        <div class="form-group">
          <label for="input-datetime">Date / Time Taken</label>
          <input type="datetime-local" id="input-datetime">
        </div>

        <div class="form-group">
          <label for="input-description">Description / Caption</label>
          <textarea id="input-description" placeholder="Describe the photo…"></textarea>
        </div>

        <div class="form-group">
          <label for="input-keywords">Keywords / Tags</label>
          <input type="text" id="input-keywords" placeholder="travel, landscape, nature (comma-separated)">
        </div>

        <div class="form-group">
          <label for="input-copyright">Copyright</label>
          <input type="text" id="input-copyright" placeholder="© 2025 Your Name">
        </div>
      </div>

      <!-- Batch Progress Section -->
      <div class="panel-section hidden" id="batch-section">
        <div class="section-title">Batch Files</div>
        <div id="batch-list" class="batch-list"></div>
      </div>

      <!-- Options Section -->
      <div class="panel-section">
        <div class="section-title">Options</div>

        <div class="toggle-row">
          <div>
            <span>Remove All EXIF Data</span>
            <small>Privacy mode — strips all metadata before download</small>
          </div>
          <label class="switch">
            <input type="checkbox" id="strip-toggle">
            <span class="switch-slider"></span>
          </label>
        </div>
      </div>

      <!-- Download Section -->
      <div class="panel-section">
        <button type="submit" class="btn btn-primary" id="btn-download" disabled>
          ⬇ Download Geotagged Photo
        </button>
        <p class="text-sm text-muted mt-2" style="text-align:center;">
          All processing happens on the server. Your photos are never stored.
        </p>
      </div>

    </form><!-- /geotag-form -->
  </aside>

</div><!-- /app-container -->

<!-- ── Toast Container ────────────────────────────────────────────────────── -->
<div id="toast-container" class="toast-container"></div>

<!-- ── Scripts ────────────────────────────────────────────────────────────── -->

<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV/XN/WPQ=" crossorigin="anonymous"></script>

<!-- EXIF reader (for reading GPS from uploaded images before upload) -->
<script src="https://cdn.jsdelivr.net/npm/exifr@7.1.3/dist/lite.umd.js"></script>

<!-- Application JS -->
<script src="assets/app.js"></script>

<script>
// Sync the sidebar coordinate inputs with the map's hidden inputs
(function() {
  const latDisp = document.getElementById('coord-lat-disp');
  const lonDisp = document.getElementById('coord-lon-disp');
  const latInp  = document.getElementById('input-lat');
  const lonInp  = document.getElementById('input-lon');

  // Keep display inputs in sync with hidden map inputs
  const observer = new MutationObserver(() => {});
  setInterval(() => {
    if (latInp.value !== latDisp.value && document.activeElement !== latDisp) {
      latDisp.value = latInp.value;
    }
    if (lonInp.value !== lonDisp.value && document.activeElement !== lonDisp) {
      lonDisp.value = lonInp.value;
    }
  }, 250);
})();
</script>

</body>
</html>
