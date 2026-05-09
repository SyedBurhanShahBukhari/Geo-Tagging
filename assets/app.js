/**
 * GeoTagr Pro — app.js v2
 */
'use strict';

const state = {
  files: [],
  marker: null,
  lat: null,
  lon: null,
  map: null,
  dmsMode: false,
  tileType: 'street',
  streetTiles: null,
  satelliteTiles: null,
  searchTimeout: null,
  processing: false,
};

const $ = id => document.getElementById(id);

document.addEventListener('DOMContentLoaded', () => {
  initMap();
  initUpload();
  initSearch();
  initLayerToggle();
  initGeolocation();
  initDmsToggle();
});

// ── Map ───────────────────────────────────────────────────────
function initMap() {
  const map = L.map('map', { center: [20, 0], zoom: 2, zoomControl: true });

  const street = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://openstreetmap.org/copyright">OpenStreetMap</a>',
    maxZoom: 19,
  });

  const satellite = L.tileLayer(
    'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
    { attribution: '© Esri', maxZoom: 19 }
  );

  street.addTo(map);
  state.map = map;
  state.streetTiles = street;
  state.satelliteTiles = satellite;

  // Fix tiles on load
  setTimeout(() => map.invalidateSize(), 100);
  setTimeout(() => map.invalidateSize(), 600);
  window.addEventListener('resize', () => map.invalidateSize());

  map.on('click', e => placePin(e.latlng.lat, e.latlng.lng, true));
}

function placePin(lat, lon, doReverse = false) {
  state.lat = lat;
  state.lon = lon;

  const iconHtml = `
    <svg width="30" height="40" viewBox="0 0 30 40" fill="none" xmlns="http://www.w3.org/2000/svg">
      <path d="M15 0C6.716 0 0 6.716 0 15c0 9.375 13 25 15 25s15-15.625 15-25C30 6.716 23.284 0 15 0z" fill="#f97316"/>
      <circle cx="15" cy="15" r="6" fill="white"/>
    </svg>`;

  if (state.marker) {
    state.marker.setLatLng([lat, lon]);
  } else {
    const icon = L.divIcon({
      html: iconHtml,
      className: 'custom-pin pin-drop',
      iconSize: [30, 40],
      iconAnchor: [15, 40],
    });
    state.marker = L.marker([lat, lon], { icon, draggable: true }).addTo(state.map);
    state.marker.on('dragend', e => {
      const p = e.target.getLatLng();
      placePin(p.lat, p.lng, true);
    });
  }

  updateCoordFields(lat, lon);
  syncDispFields(lat, lon);
  if (doReverse) reverseGeocode(lat, lon);
  updateDownloadButton();
}

function updateCoordFields(lat, lon) {
  const li = $('input-lat'), lo = $('input-lon');
  if (!li || !lo) return;
  if (state.dmsMode) {
    li.value = decimalToDms(lat, 'lat');
    lo.value = decimalToDms(lon, 'lon');
  } else {
    li.value = lat.toFixed(6);
    lo.value = lon.toFixed(6);
  }
}

function syncDispFields(lat, lon) {
  const ld = $('coord-lat-disp'), lo = $('coord-lon-disp');
  if (ld) ld.value = state.dmsMode ? decimalToDms(lat, 'lat') : lat.toFixed(6);
  if (lo) lo.value = state.dmsMode ? decimalToDms(lon, 'lon') : lon.toFixed(6);
}

function decimalToDms(d, axis) {
  const abs = Math.abs(d), deg = Math.floor(abs);
  const mf  = (abs - deg) * 60, min = Math.floor(mf);
  const sec = ((mf - min) * 60).toFixed(2);
  const dir = axis === 'lat' ? (d >= 0 ? 'N' : 'S') : (d >= 0 ? 'E' : 'W');
  return `${deg}° ${min}' ${sec}" ${dir}`;
}

function dmsToDecimal(s) {
  const c = s.replace(/[°'"]/g, ' ').replace(/\s+/g, ' ').trim().split(' ');
  let deg = 0, min = 0, sec = 0, dir = '';
  if (c.length >= 4) { deg = +c[0]; min = +c[1]; sec = +c[2]; dir = c[3].toUpperCase(); }
  else if (c.length === 2) { deg = +c[0]; dir = c[1].toUpperCase(); }
  let v = deg + min / 60 + sec / 3600;
  if (dir === 'S' || dir === 'W') v = -v;
  return v;
}

// ── Upload ─────────────────────────────────────────────────────
function initUpload() {
  const zone = $('upload-zone'), inp = $('file-input');

  zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
  zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
  zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.classList.remove('dragover');
    handleFiles(Array.from(e.dataTransfer.files));
  });
  zone.addEventListener('click', e => { if (e.target !== inp) inp.click(); });
  inp.addEventListener('change', e => { handleFiles(Array.from(e.target.files)); e.target.value = ''; });
}

async function handleFiles(newFiles) {
  const allowed = ['image/jpeg','image/jpg','image/png','image/webp','image/heic','image/heif'];
  const allowedExts = ['jpg','jpeg','png','webp','heic','heif'];
  const MAX = 15;
  const isSingle = window._uploadMode === 'single';

  const valid = newFiles.filter(f => {
    const ext = f.name.split('.').pop().toLowerCase();
    return allowed.includes(f.type) || allowedExts.includes(ext);
  });

  if (valid.length === 0) {
    showToast('error', 'Unsupported Format', 'Please upload JPG, PNG, WebP, or HEIC files.'); return;
  }

  if (isSingle) {
    // Clear existing and take only first
    state.files.forEach(f => URL.revokeObjectURL(f.previewUrl));
    state.files = [];
  }

  const remaining = MAX - state.files.length;
  const toAdd = valid.slice(0, remaining);
  if (valid.length > remaining) {
    showToast('warn', 'Limit Reached', `Max ${MAX} files. Added ${toAdd.length}.`);
  }

  for (const file of toAdd) {
    const id  = 'f_' + Date.now() + '_' + Math.random().toString(36).slice(2);
    const url = URL.createObjectURL(file);
    const entry = { file, previewUrl: url, exifGps: null, id };

    try {
      if (typeof exifr !== 'undefined') {
        const gps = await exifr.gps(file);
        if (gps?.latitude !== undefined) entry.exifGps = { lat: gps.latitude, lon: gps.longitude };
      }
    } catch (_) {}

    state.files.push(entry);
  }

  renderPreviews();
  updateDownloadButton();

  // Auto-place pin from GPS if none set
  if (state.lat === null) {
    const withGps = state.files.find(f => f.exifGps);
    if (withGps) {
      const { lat, lon } = withGps.exifGps;
      placePin(lat, lon, true);
      state.map.setView([lat, lon], 12);
      showToast('success', 'GPS Found', `Loaded existing coordinates: ${lat.toFixed(5)}, ${lon.toFixed(5)}`);
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
    item.innerHTML = `
      <img src="${entry.previewUrl}" alt="${entry.file.name}">
      ${entry.exifGps ? '<div class="exif-badge">GPS</div>' : ''}
      <button class="remove-btn" data-idx="${idx}" title="Remove">✕</button>
      <div class="file-status"><div class="file-progress" id="prog_${entry.id}"></div></div>
      <span class="status-icon" id="si_${entry.id}"></span>`;
    grid.appendChild(item);
  });

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
  renderBatchList();
}

function renderBatchList() {
  const list = $('batch-list'), sec = $('batch-section'), cnt = $('file-count');
  if (!list) return;

  const show = state.files.length > 1 || window._uploadMode === 'bulk';
  sec.classList.toggle('hidden', !show);
  if (cnt) cnt.textContent = `(${state.files.length} / 15)`;

  list.innerHTML = '';
  state.files.forEach(entry => {
    const li = document.createElement('div');
    li.className = 'batch-item';
    li.id = 'bi_' + entry.id;
    const mb = (entry.file.size / 1024 / 1024).toFixed(1);
    li.innerHTML = `
      <span class="bi-status" id="bst_${entry.id}">📷</span>
      <span class="bi-name" title="${entry.file.name}">${entry.file.name}</span>
      <span class="bi-size">${mb} MB</span>
      <div class="bi-progress-bar" style="grid-column:1/-1"><div class="bi-progress-fill" id="bpf_${entry.id}"></div></div>`;
    list.appendChild(li);
  });
}

function showExistingGps() {
  const box = $('existing-gps'), val = $('gps-val');
  const withGps = state.files.find(f => f.exifGps);
  if (withGps?.exifGps && box && val) {
    val.textContent = `${withGps.exifGps.lat.toFixed(6)}, ${withGps.exifGps.lon.toFixed(6)}`;
    box.classList.add('visible');
  } else if (box) {
    box.classList.remove('visible');
  }
}

// ── Search ─────────────────────────────────────────────────────
function initSearch() {
  const inp = $('search-input'), res = $('search-results');

  inp.addEventListener('input', () => {
    clearTimeout(state.searchTimeout);
    const q = inp.value.trim();
    if (q.length < 3) { res.classList.remove('visible'); return; }
    state.searchTimeout = setTimeout(() => geocodeSearch(q), 500);
  });

  inp.addEventListener('keydown', e => { if (e.key === 'Escape') res.classList.remove('visible'); });
  document.addEventListener('click', e => {
    if (!res.contains(e.target) && e.target !== inp) res.classList.remove('visible');
  });
}

async function geocodeSearch(q) {
  const res = $('search-results');
  res.innerHTML = '<div class="search-result-item"><span>Searching…</span></div>';
  res.classList.add('visible');

  try {
    const data = await fetch(`geocode.php?q=${encodeURIComponent(q)}`).then(r => r.json());
    if (!Array.isArray(data) || data.length === 0) {
      res.innerHTML = '<div class="search-result-item"><span>No results found.</span></div>'; return;
    }

    res.innerHTML = '';
    data.forEach(item => {
      const el = document.createElement('div');
      el.className = 'search-result-item';
      const name  = item.display_name || 'Unknown';
      const short = name.split(',').slice(0, 3).join(', ');
      el.innerHTML = `<strong>${short}</strong><span>${name}</span>`;
      el.addEventListener('click', () => {
        const lat = parseFloat(item.lat), lon = parseFloat(item.lon);
        placePin(lat, lon);
        state.map.setView([lat, lon], 13, { animate: true });
        $('search-input').value = short;
        res.classList.remove('visible');
        updateReverseAddr(name);
      });
      res.appendChild(el);
    });
  } catch {
    res.innerHTML = '<div class="search-result-item"><span>Search failed.</span></div>';
  }
}

async function reverseGeocode(lat, lon) {
  const addr = $('reverse-addr');
  if (!addr) return;
  addr.textContent = 'Loading address…';
  addr.classList.remove('loaded');
  try {
    const data = await fetch(`geocode.php?lat=${lat}&lon=${lon}`).then(r => r.json());
    if (data.display_name) updateReverseAddr(data.display_name.split(',').slice(0, 4).join(', '));
  } catch { addr.textContent = ''; }
}

function updateReverseAddr(text) {
  const addr = $('reverse-addr');
  if (!addr) return;
  addr.textContent = text;
  addr.classList.add('loaded');
}

// ── Layers ─────────────────────────────────────────────────────
function initLayerToggle() {
  $('btn-street').addEventListener('click', () => {
    if (state.tileType === 'street') return;
    state.map.removeLayer(state.satelliteTiles);
    state.streetTiles.addTo(state.map);
    state.tileType = 'street';
    $('btn-street').classList.add('active');
    $('btn-satellite').classList.remove('active');
  });
  $('btn-satellite').addEventListener('click', () => {
    if (state.tileType === 'satellite') return;
    state.map.removeLayer(state.streetTiles);
    state.satelliteTiles.addTo(state.map);
    state.tileType = 'satellite';
    $('btn-satellite').classList.add('active');
    $('btn-street').classList.remove('active');
  });
}

// ── Geolocation ────────────────────────────────────────────────
function initGeolocation() {
  $('btn-geolocate').addEventListener('click', () => {
    if (!navigator.geolocation) { showToast('error', 'Not Supported', 'Geolocation unavailable.'); return; }
    $('btn-geolocate').textContent = '⏳';
    navigator.geolocation.getCurrentPosition(
      pos => {
        $('btn-geolocate').textContent = '📍';
        const { latitude: lat, longitude: lon } = pos.coords;
        placePin(lat, lon, true);
        state.map.setView([lat, lon], 14, { animate: true });
        showToast('success', 'Location Found', `${lat.toFixed(5)}, ${lon.toFixed(5)}`);
      },
      err => { $('btn-geolocate').textContent = '📍'; showToast('error', 'Location Error', err.message); },
      { timeout: 10000, maximumAge: 60000 }
    );
  });
}

// ── DMS Toggle ─────────────────────────────────────────────────
function initDmsToggle() {
  $('dms-toggle').addEventListener('click', () => {
    state.dmsMode = !state.dmsMode;
    $('dms-toggle').textContent = state.dmsMode ? 'DD' : 'DMS';
    $('dms-toggle').classList.toggle('active', state.dmsMode);
    if (state.lat !== null) { updateCoordFields(state.lat, state.lon); syncDispFields(state.lat, state.lon); }
  });

  [$('input-lat'), $('input-lon')].forEach(inp => {
    inp.addEventListener('change', () => {
      const li = $('input-lat'), lo = $('input-lon');
      const lat = state.dmsMode ? dmsToDecimal(li.value) : parseFloat(li.value);
      const lon = state.dmsMode ? dmsToDecimal(lo.value) : parseFloat(lo.value);
      if (isNaN(lat) || isNaN(lon)) return;
      if (lat < -90 || lat > 90 || lon < -180 || lon > 180) {
        showToast('error', 'Invalid Coords', 'Lat: -90 to 90, Lon: -180 to 180'); return;
      }
      state.lat = lat; state.lon = lon;
      placePin(lat, lon, true);
      state.map.setView([lat, lon], 12, { animate: true });
    });
  });
}

// ── Download button state ──────────────────────────────────────
function updateDownloadButton() {
  const btn  = $('btn-download');
  const strip = $('strip-toggle')?.checked;
  const hasFiles  = state.files.length > 0;
  const hasCoords = state.lat !== null && state.lon !== null;
  btn.disabled = !hasFiles || (!strip && !hasCoords);
  btn.classList.toggle('ready', hasFiles && (strip || hasCoords));

  const label = state.files.length > 1
    ? `⬇ Download ${state.files.length} Photos (ZIP)`
    : '⬇ Download Geotagged Photo';
  btn.textContent = label;
}

// ── Form Submit ────────────────────────────────────────────────
function submitForm() {
  if (state.processing || state.files.length === 0) {
    showToast('error', 'No Files', 'Please upload at least one image.'); return;
  }

  const strip = $('strip-toggle').checked;
  if (!strip && (state.lat === null || state.lon === null)) {
    showToast('error', 'No Location', 'Click the map to set coordinates first.'); return;
  }

  state.processing = true;
  const btn = $('btn-download');
  btn.disabled = true;
  btn.textContent = '⏳ Processing…';

  const fd = new FormData();
  if (!strip) {
    fd.append('lat', state.lat);
    fd.append('lon', state.lon);
    fd.append('alt', $('input-alt')?.value || '0');
    if ($('write-meta')?.checked) {
      fd.append('description', $('input-description')?.value || '');
      fd.append('keywords',    $('input-keywords')?.value    || '');
      fd.append('copyright',   $('input-copyright')?.value   || '');
    }
    fd.append('datetime', $('input-datetime')?.value || '');
  } else {
    fd.append('lat', '0'); fd.append('lon', '0');
    fd.append('strip_exif', '1');
  }

  state.files.forEach(entry => {
    fd.append('files[]', entry.file, entry.file.name);
    setFileStatus(entry.id, '⏳', 0);
  });

  const xhr = new XMLHttpRequest();
  xhr.open('POST', 'upload.php', true);
  xhr.responseType = 'blob';

  xhr.upload.addEventListener('progress', e => {
    if (!e.lengthComputable) return;
    const pct = Math.round(e.loaded / e.total * 100);
    state.files.forEach(entry => setFileProgress(entry.id, Math.min(pct * 0.8, 80)));
  });

  xhr.addEventListener('load', async () => {
    state.processing = false;
    if (xhr.status === 200) {
      const cd = xhr.getResponseHeader('Content-Disposition') || '';
      const m  = cd.match(/filename="?([^"]+)"?/);
      const fname = m ? m[1] : (state.files.length > 1 ? 'geotagr_batch.zip' : state.files[0]?.file.name || 'geotagged.jpg');
      state.files.forEach(e => { setFileProgress(e.id, 100); setFileStatus(e.id, '✅'); });
      showToast('success', 'Done!', `Downloading ${fname}`);
      downloadBlob(xhr.response, fname);

      try {
        const errs = JSON.parse(xhr.getResponseHeader('X-GeoTagr-Errors') || '[]');
        if (errs.length) showToast('warn', 'Partial Errors', errs.slice(0,2).join('; '));
      } catch (_) {}
    } else {
      state.files.forEach(e => setFileStatus(e.id, '❌'));
      let msg = 'Upload failed.';
      try { msg = JSON.parse(await xhr.response.text()).error || msg; } catch (_) {}
      showToast('error', 'Failed', msg);
    }
    updateDownloadButton();
  });

  xhr.addEventListener('error', () => {
    state.processing = false;
    state.files.forEach(e => setFileStatus(e.id, '❌'));
    showToast('error', 'Network Error', 'Could not reach server.');
    updateDownloadButton();
  });

  xhr.send(fd);
}

function setFileProgress(id, pct) {
  [$(`prog_${id}`), $(`bpf_${id}`)].forEach(el => { if (el) el.style.width = pct + '%'; });
}

function setFileStatus(id, icon, prog = null) {
  [$(`si_${id}`), $(`bst_${id}`)].forEach(el => { if (el) el.textContent = icon; });
  if (prog !== null) setFileProgress(id, prog);
}

function downloadBlob(blob, filename) {
  const url = URL.createObjectURL(blob);
  const a = Object.assign(document.createElement('a'), { href: url, download: filename });
  document.body.appendChild(a);
  a.click();
  setTimeout(() => { URL.revokeObjectURL(url); a.remove(); }, 1000);
}

function showToast(type, title, message, duration = 4500) {
  const icons = { success: '✓', error: '✕', warn: '⚠' };
  const t = document.createElement('div');
  t.className = `toast ${type}`;
  t.innerHTML = `
    <span class="toast-icon">${icons[type]||'●'}</span>
    <div class="toast-body">
      <div class="toast-title">${title}</div>
      ${message ? `<div class="toast-msg">${message}</div>` : ''}
    </div>`;
  $('toast-container').appendChild(t);
  const hide = () => { t.classList.add('hiding'); setTimeout(() => t.remove(), 300); };
  t.addEventListener('click', hide);
  setTimeout(hide, duration);
}
