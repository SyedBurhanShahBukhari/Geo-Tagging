/**
 * GeoTagr Pro — app.js
 * Full application logic: map, upload, EXIF reading, form handling
 */

'use strict';

// ── State ─────────────────────────────────────────────────────────────────────
const state = {
  files: [],      // { file, previewUrl, exifGps, batchId }
  marker: null,
  lat: null,
  lon: null,
  map: null,
  dmsMode: false,
  tileLayer: null,
  tileType: 'street',
  searchTimeout: null,
  processing: false,
};

// ── DOM Refs ──────────────────────────────────────────────────────────────────
const $ = id => document.getElementById(id);

// ── Init ──────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  initMap();
  initUpload();
  initForm();
  initSearch();
  initLayerToggle();
  initGeolocation();
  initDmsToggle();
  initStripToggle();
});

// ── Map Initialization ────────────────────────────────────────────────────────
function initMap() {
  const map = L.map('map', {
    center: [20, 0],
    zoom: 2,
    zoomControl: true,
  });

  const streetTiles = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19,
  });

  const satelliteTiles = L.tileLayer(
    'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
    attribution: '© Esri',
    maxZoom: 19,
  });

  streetTiles.addTo(map);
  state.map = map;
  state.tileLayer = streetTiles;
  state.streetTiles = streetTiles;
  state.satelliteTiles = satelliteTiles;

  // Force tile refresh after container finishes rendering
  setTimeout(() => map.invalidateSize(), 100);
  setTimeout(() => map.invalidateSize(), 500);

  // Re-invalidate on window resize
  window.addEventListener('resize', () => map.invalidateSize());

  // Click on map to place pin
  map.on('click', e => {
    placePin(e.latlng.lat, e.latlng.lng, true);
  });
}

// ── Pin Management ────────────────────────────────────────────────────────────
function placePin(lat, lon, doReverse = false) {
  state.lat = lat;
  state.lon = lon;

  const iconHtml = `
    <svg width="32" height="42" viewBox="0 0 32 42" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M16 0C7.163 0 0 7.163 0 16c0 9.941 14 26 16 26s16-16.059 16-26C32 7.163 24.837 0 16 0z" fill="#00d4aa"/>
      <circle cx="16" cy="16" r="6" fill="#0a0f1e"/>
    </svg>`;

  if (state.marker) {
    state.marker.setLatLng([lat, lon]);
  } else {
    const icon = L.divIcon({
      html: iconHtml,
      className: 'custom-pin pin-drop',
      iconSize: [32, 42],
      iconAnchor: [16, 42],
    });
    state.marker = L.marker([lat, lon], { icon, draggable: true }).addTo(state.map);

    state.marker.on('dragend', e => {
      const pos = e.target.getLatLng();
      placePin(pos.lat, pos.lng, true);
    });
  }

  updateCoordFields(lat, lon);

  if (doReverse) reverseGeocode(lat, lon);
}

function updateCoordFields(lat, lon) {
  const latInput = $('input-lat');
  const lonInput = $('input-lon');
  if (!latInput || !lonInput) return;

  if (state.dmsMode) {
    latInput.value = decimalToDms(lat, 'lat');
    lonInput.value = decimalToDms(lon, 'lon');
  } else {
    latInput.value = lat.toFixed(6);
    lonInput.value = lon.toFixed(6);
  }
}

function decimalToDms(decimal, axis) {
  const abs     = Math.abs(decimal);
  const deg     = Math.floor(abs);
  const minFull = (abs - deg) * 60;
  const min     = Math.floor(minFull);
  const sec     = ((minFull - min) * 60).toFixed(2);
  let dir;
  if (axis === 'lat') dir = decimal >= 0 ? 'N' : 'S';
  else                dir = decimal >= 0 ? 'E' : 'W';
  return `${deg}° ${min}' ${sec}" ${dir}`;
}

function dmsToDecimal(dms) {
  // Accepts formats: "40° 26' 46.56\" N" or "40,26,46.56N"
  const clean = dms.replace(/[°'"]/g, ' ').replace(/\s+/g, ' ').trim();
  const parts = clean.split(' ');
  let deg = 0, min = 0, sec = 0, dir = '';

  if (parts.length >= 4) {
    deg = parseFloat(parts[0]) || 0;
    min = parseFloat(parts[1]) || 0;
    sec = parseFloat(parts[2]) || 0;
    dir = parts[3].toUpperCase();
  } else if (parts.length === 2) {
    // "40.1234 N"
    deg = parseFloat(parts[0]) || 0;
    dir = parts[1].toUpperCase();
  }

  let decimal = deg + min / 60 + sec / 3600;
  if (dir === 'S' || dir === 'W') decimal = -decimal;
  return decimal;
}

// ── Upload Zone ───────────────────────────────────────────────────────────────
function initUpload() {
  const zone    = $('upload-zone');
  const fileInp = $('file-input');

  zone.addEventListener('dragover', e => {
    e.preventDefault();
    zone.classList.add('dragover');
  });

  zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));

  zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.classList.remove('dragover');
    handleFiles(Array.from(e.dataTransfer.files));
  });

  // Click on zone (not the hidden input) triggers file picker
  zone.addEventListener('click', e => {
    if (e.target === fileInp) return;
    fileInp.click();
  });

  fileInp.addEventListener('change', e => {
    handleFiles(Array.from(e.target.files));
    e.target.value = '';
  });
}

async function handleFiles(newFiles) {
  const allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];
  const maxFiles = 10;

  const valid = newFiles.filter(f => {
    const ext = f.name.split('.').pop().toLowerCase();
    return allowed.includes(f.type) || ['jpg','jpeg','png','webp','heic','heif'].includes(ext);
  });

  if (valid.length === 0) {
    showToast('error', 'Unsupported Format', 'Please upload JPG, PNG, WebP, or HEIC files.');
    return;
  }

  const remaining = maxFiles - state.files.length;
  const toAdd = valid.slice(0, remaining);

  if (valid.length > remaining) {
    showToast('warn', 'Limit Reached', `Max 10 files. Added ${toAdd.length}.`);
  }

  for (const file of toAdd) {
    const id = 'f_' + Date.now() + '_' + Math.random().toString(36).slice(2);
    const url = URL.createObjectURL(file);
    const entry = { file, previewUrl: url, exifGps: null, id };

    // Read EXIF GPS asynchronously
    try {
      if (typeof exifr !== 'undefined') {
        const gps = await exifr.gps(file);
        if (gps && gps.latitude !== undefined) {
          entry.exifGps = { lat: gps.latitude, lon: gps.longitude };
        }
      }
    } catch (_) {}

    state.files.push(entry);
  }

  renderPreviews();
  updateDownloadButton();

  // If first file has GPS and no pin placed yet
  if (state.files.length > 0 && state.lat === null) {
    const withGps = state.files.find(f => f.exifGps);
    if (withGps) {
      const { lat, lon } = withGps.exifGps;
      placePin(lat, lon, true);
      state.map.setView([lat, lon], 12);
      showToast('success', 'GPS Found', `Existing coordinates loaded: ${lat.toFixed(5)}, ${lon.toFixed(5)}`);
    }
  }

  showExistingGps();
}

function renderPreviews() {
  const grid = $('preview-grid');
  grid.innerHTML = '';

  state.files.forEach((entry, idx) => {
    const item = document.createElement('div');
    item.className = 'preview-item';
    item.dataset.id = entry.id;

    item.innerHTML = `
      <img src="${entry.previewUrl}" alt="${entry.file.name}">
      ${entry.exifGps ? '<div class="exif-badge">GPS</div>' : ''}
      <button class="remove-btn" data-idx="${idx}" title="Remove">✕</button>
      <div class="file-status"><div class="file-progress" id="prog_${entry.id}"></div></div>
      <span class="status-icon" id="si_${entry.id}"></span>`;

    grid.appendChild(item);
  });

  // Batch items in sidebar
  renderBatchList();

  // Remove button handlers
  grid.querySelectorAll('.remove-btn').forEach(btn => {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      const idx = parseInt(btn.dataset.idx);
      URL.revokeObjectURL(state.files[idx].previewUrl);
      state.files.splice(idx, 1);
      renderPreviews();
      updateDownloadButton();
      showExistingGps();
    });
  });

  $('preview-area').classList.toggle('hidden', state.files.length === 0);
}

function renderBatchList() {
  const list = $('batch-list');
  if (!list) return;

  list.innerHTML = '';
  state.files.forEach(entry => {
    const li = document.createElement('div');
    li.className = 'batch-item';
    li.id = 'bi_' + entry.id;
    const sizeMb = (entry.file.size / 1024 / 1024).toFixed(1);
    li.innerHTML = `
      <span class="bi-status" id="bst_${entry.id}">📷</span>
      <span class="bi-name" title="${entry.file.name}">${entry.file.name}</span>
      <span class="bi-size">${sizeMb} MB</span>
      <div class="bi-progress-bar"><div class="bi-progress-fill" id="bpf_${entry.id}"></div></div>`;
    list.appendChild(li);
  });

  $('batch-section').classList.toggle('hidden', state.files.length <= 1);
}

function showExistingGps() {
  const box  = $('existing-gps');
  if (!box) return;

  const withGps = state.files.find(f => f.exifGps);
  if (withGps && withGps.exifGps) {
    const { lat, lon } = withGps.exifGps;
    box.querySelector('.gps-values').textContent = `${lat.toFixed(6)}, ${lon.toFixed(6)}`;
    box.classList.add('visible');
  } else {
    box.classList.remove('visible');
  }
}

// ── Search ────────────────────────────────────────────────────────────────────
function initSearch() {
  const inp     = $('search-input');
  const results = $('search-results');

  inp.addEventListener('input', () => {
    clearTimeout(state.searchTimeout);
    const q = inp.value.trim();
    if (q.length < 3) { results.classList.remove('visible'); return; }

    state.searchTimeout = setTimeout(() => geocodeSearch(q), 500);
  });

  inp.addEventListener('keydown', e => {
    if (e.key === 'Escape') results.classList.remove('visible');
  });

  document.addEventListener('click', e => {
    if (!results.contains(e.target) && e.target !== inp) {
      results.classList.remove('visible');
    }
  });
}

async function geocodeSearch(q) {
  const results = $('search-results');
  results.innerHTML = '<div class="search-result-item"><span>Searching…</span></div>';
  results.classList.add('visible');

  try {
    const res  = await fetch(`geocode.php?q=${encodeURIComponent(q)}`);
    const data = await res.json();

    if (!Array.isArray(data) || data.length === 0) {
      results.innerHTML = '<div class="search-result-item"><span>No results found.</span></div>';
      return;
    }

    results.innerHTML = '';
    data.forEach(item => {
      const el = document.createElement('div');
      el.className = 'search-result-item';
      const name = item.display_name || 'Unknown';
      const short = name.split(',').slice(0, 3).join(', ');
      el.innerHTML = `<strong>${short}</strong><span>${name}</span>`;
      el.addEventListener('click', () => {
        const lat = parseFloat(item.lat);
        const lon = parseFloat(item.lon);
        placePin(lat, lon);
        state.map.setView([lat, lon], 13, { animate: true });
        $('search-input').value = short;
        results.classList.remove('visible');
        updateReverseAddr(name);
      });
      results.appendChild(el);
    });
  } catch {
    results.innerHTML = '<div class="search-result-item"><span>Search failed. Check connection.</span></div>';
  }
}

async function reverseGeocode(lat, lon) {
  const addr = $('reverse-addr');
  if (!addr) return;
  addr.textContent = 'Loading address…';
  addr.classList.remove('loaded');

  try {
    const res  = await fetch(`geocode.php?lat=${lat}&lon=${lon}`);
    const data = await res.json();
    if (data.display_name) {
      const short = data.display_name.split(',').slice(0, 4).join(', ');
      updateReverseAddr(short);
    }
  } catch {
    addr.textContent = '';
  }
}

function updateReverseAddr(text) {
  const addr = $('reverse-addr');
  if (!addr) return;
  addr.textContent = text;
  addr.classList.add('loaded');
}

// ── Layer Toggle ──────────────────────────────────────────────────────────────
function initLayerToggle() {
  const btnStreet = $('btn-street');
  const btnSat    = $('btn-satellite');

  btnStreet.addEventListener('click', () => {
    if (state.tileType === 'street') return;
    state.map.removeLayer(state.satelliteTiles);
    state.streetTiles.addTo(state.map);
    state.tileType = 'street';
    btnStreet.classList.add('active');
    btnSat.classList.remove('active');
  });

  btnSat.addEventListener('click', () => {
    if (state.tileType === 'satellite') return;
    state.map.removeLayer(state.streetTiles);
    state.satelliteTiles.addTo(state.map);
    state.tileType = 'satellite';
    btnSat.classList.add('active');
    btnStreet.classList.remove('active');
  });
}

// ── Geolocation ───────────────────────────────────────────────────────────────
function initGeolocation() {
  const btn = $('btn-geolocate');
  btn.addEventListener('click', () => {
    if (!navigator.geolocation) {
      showToast('error', 'Not Supported', 'Geolocation is not available in your browser.');
      return;
    }
    btn.textContent = '⏳';
    navigator.geolocation.getCurrentPosition(
      pos => {
        btn.textContent = '📍';
        const { latitude: lat, longitude: lon } = pos.coords;
        placePin(lat, lon, true);
        state.map.setView([lat, lon], 14, { animate: true });
        showToast('success', 'Location Found', `${lat.toFixed(5)}, ${lon.toFixed(5)}`);
      },
      err => {
        btn.textContent = '📍';
        showToast('error', 'Location Error', err.message);
      },
      { timeout: 10000, maximumAge: 60000 }
    );
  });
}

// ── DMS Toggle ────────────────────────────────────────────────────────────────
function initDmsToggle() {
  const btn     = $('dms-toggle');
  const latInp  = $('input-lat');
  const lonInp  = $('input-lon');

  btn.addEventListener('click', () => {
    state.dmsMode = !state.dmsMode;
    btn.textContent  = state.dmsMode ? 'DD' : 'DMS';
    btn.classList.toggle('active', state.dmsMode);
    if (state.lat !== null) updateCoordFields(state.lat, state.lon);
  });

  // Manual lat/lon input
  [latInp, lonInp].forEach(inp => {
    inp.addEventListener('change', () => syncCoordsFromInput());
  });
}

function syncCoordsFromInput() {
  const latInp = $('input-lat');
  const lonInp = $('input-lon');
  let lat, lon;

  if (state.dmsMode) {
    lat = dmsToDecimal(latInp.value);
    lon = dmsToDecimal(lonInp.value);
  } else {
    lat = parseFloat(latInp.value);
    lon = parseFloat(lonInp.value);
  }

  if (isNaN(lat) || isNaN(lon)) return;
  if (lat < -90 || lat > 90 || lon < -180 || lon > 180) {
    showToast('error', 'Invalid Coords', 'Latitude must be -90 to 90, longitude -180 to 180.');
    return;
  }

  state.lat = lat;
  state.lon = lon;
  placePin(lat, lon, true);
  state.map.setView([lat, lon], 12, { animate: true });
}

// ── Strip Mode ────────────────────────────────────────────────────────────────
function initStripToggle() {
  const toggle  = $('strip-toggle');
  const coordSec = $('coords-section');

  toggle.addEventListener('change', () => {
    coordSec.style.opacity = toggle.checked ? '0.4' : '1';
    coordSec.style.pointerEvents = toggle.checked ? 'none' : 'auto';
    updateDownloadButton();
  });
}

// ── Form ──────────────────────────────────────────────────────────────────────
function initForm() {
  const form = $('geotag-form');
  form.addEventListener('submit', e => {
    e.preventDefault();
    if (state.processing) return;
    submitForm();
  });
}

function updateDownloadButton() {
  const btn = $('btn-download');
  const stripMode = $('strip-toggle')?.checked;
  const hasFiles  = state.files.length > 0;
  const hasCoords = state.lat !== null && state.lon !== null;

  btn.disabled = !hasFiles || (!stripMode && !hasCoords);
  btn.classList.toggle('ready', hasFiles && (stripMode || hasCoords));
}

async function submitForm() {
  if (state.files.length === 0) {
    showToast('error', 'No Files', 'Please upload at least one image.');
    return;
  }

  const stripMode = $('strip-toggle').checked;

  if (!stripMode && (state.lat === null || state.lon === null)) {
    showToast('error', 'No Location', 'Please click the map or search to set coordinates.');
    return;
  }

  state.processing = true;
  $('btn-download').disabled = true;
  $('btn-download').textContent = 'Processing…';

  const formData = new FormData();

  if (!stripMode) {
    formData.append('lat', state.lat);
    formData.append('lon', state.lon);
    formData.append('alt', $('input-alt')?.value || '0');
    formData.append('keywords',    $('input-keywords')?.value    || '');
    formData.append('description', $('input-description')?.value || '');
    formData.append('datetime',    $('input-datetime')?.value    || '');
    formData.append('copyright',   $('input-copyright')?.value   || '');
  } else {
    formData.append('lat', '0');
    formData.append('lon', '0');
    formData.append('strip_exif', '1');
  }

  state.files.forEach((entry, i) => {
    formData.append('files[]', entry.file, entry.file.name);
    setFileStatus(entry.id, '⏳', 0);
  });

  // Use XMLHttpRequest for progress events
  const xhr = new XMLHttpRequest();
  xhr.open('POST', 'upload.php', true);
  xhr.responseType = 'blob';

  xhr.upload.addEventListener('progress', e => {
    if (e.lengthComputable) {
      const pct = Math.round(e.loaded / e.total * 100);
      // Distribute progress evenly across files during upload phase
      state.files.forEach(entry => {
        const fill = Math.min(pct * 0.8, 80); // upload = 0–80%
        setFileProgress(entry.id, fill);
      });
    }
  });

  xhr.addEventListener('load', async () => {
    state.processing = false;

    if (xhr.status === 200) {
      const blob = xhr.response;
      const cd   = xhr.getResponseHeader('Content-Disposition') || '';
      let fname   = 'geotagged_photo';
      const m     = cd.match(/filename="?([^"]+)"?/);
      if (m) fname = m[1];
      else if (state.files.length > 1) fname = 'geotagr_batch.zip';
      else fname = state.files[0]?.file.name || 'geotagged.jpg';

      // Mark all complete
      state.files.forEach(entry => {
        setFileProgress(entry.id, 100);
        setFileStatus(entry.id, '✅');
      });

      showToast('success', 'Done!', `Downloading ${fname}`);
      downloadBlob(blob, fname);

      // Check for partial errors in response header
      const errHeader = xhr.getResponseHeader('X-GeoTagr-Errors');
      if (errHeader) {
        try {
          const errs = JSON.parse(errHeader);
          if (errs && errs.length > 0) {
            showToast('warn', 'Partial Errors', errs.slice(0, 2).join('; '));
          }
        } catch (_) {}
      }
    } else {
      // Try to parse error JSON
      const text = await xhr.response.text();
      let msg = 'Upload failed. Please try again.';
      try { msg = JSON.parse(text).error || msg; } catch (_) {}

      state.files.forEach(entry => setFileStatus(entry.id, '❌'));
      showToast('error', 'Upload Failed', msg);
    }

    $('btn-download').disabled = false;
    $('btn-download').textContent = '⬇ Download Geotagged Photo';
    updateDownloadButton();
  });

  xhr.addEventListener('error', () => {
    state.processing = false;
    state.files.forEach(entry => setFileStatus(entry.id, '❌'));
    showToast('error', 'Network Error', 'Could not reach the server. Check your connection.');
    $('btn-download').disabled = false;
    $('btn-download').textContent = '⬇ Download Geotagged Photo';
    updateDownloadButton();
  });

  xhr.send(formData);
}

function setFileProgress(id, pct) {
  const el  = $(`prog_${id}`);
  const el2 = $(`bpf_${id}`);
  if (el)  el.style.width  = pct + '%';
  if (el2) el2.style.width = pct + '%';
}

function setFileStatus(id, icon, progress = null) {
  const si  = $(`si_${id}`);
  const bst = $(`bst_${id}`);
  if (si)  si.textContent  = icon;
  if (bst) bst.textContent = icon;
  if (progress !== null) setFileProgress(id, progress);
}

// ── Download ──────────────────────────────────────────────────────────────────
function downloadBlob(blob, filename) {
  const url = URL.createObjectURL(blob);
  const a   = document.createElement('a');
  a.href     = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  setTimeout(() => { URL.revokeObjectURL(url); a.remove(); }, 1000);
}

// ── Toast Notifications ───────────────────────────────────────────────────────
function showToast(type, title, message, duration = 4500) {
  const container = $('toast-container');
  const icons = { success: '✓', error: '✕', warn: '⚠' };

  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.innerHTML = `
    <span class="toast-icon">${icons[type] || '●'}</span>
    <div class="toast-body">
      <div class="toast-title">${title}</div>
      ${message ? `<div class="toast-msg">${message}</div>` : ''}
    </div>`;

  container.appendChild(toast);

  const hide = () => {
    toast.classList.add('hiding');
    setTimeout(() => toast.remove(), 300);
  };

  toast.addEventListener('click', hide);
  setTimeout(hide, duration);
}
