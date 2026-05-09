# GeoTagr Pro

A professional photo geotagging web application. Embed GPS coordinates into image EXIF data directly from your browser — free, private, no sign-in required.

## Features

- **Interactive Leaflet map** — click to place a pin, drag to adjust, search by address
- **Batch processing** — up to 10 photos at once, ZIP download
- **EXIF GPS writing** — latitude, longitude, altitude for JPEG files (via PEL library)
- **XMP metadata** — GPS + keywords for PNG and WebP
- **EXIF Strip mode** — remove all metadata for privacy
- **Metadata fields** — keywords, description, date/time, copyright
- **Satellite/Street map toggle**
- **Reverse geocoding** — see address when you drop a pin
- **DMS ↔ Decimal** coordinate display toggle
- **No ads, no watermarks, no sign-in**

---

## Hostinger Deployment Steps

### 1. Upload Files

**Via File Manager (easiest):**
1. Log in to your Hostinger control panel
2. Go to **Files → File Manager**
3. Navigate to `public_html/` (or a subdirectory like `public_html/geotag/`)
4. Upload the entire project folder contents (drag & drop all files/folders)

**Via FTP:**
```
Host:     ftp.yourdomain.com
User:     your-ftp-username
Password: your-ftp-password
Port:     21
```
Upload everything to `public_html/` or a subfolder.

### 2. Verify Directory Structure

After upload, your server should have:

```
public_html/
├── index.php
├── upload.php
├── geocode.php
├── .htaccess
├── assets/
│   ├── style.css
│   └── app.js
└── lib/
    ├── GeoTagger.php
    └── pel/
        ├── PelConvert.php
        ├── PelDataWindow.php
        ├── PelEntry.php
        ├── PelEntryAscii.php
        ├── PelEntryByte.php
        ├── PelEntryLong.php
        ├── PelEntryNumber.php
        ├── PelEntryRational.php
        ├── PelEntryShort.php
        ├── PelEntryUndefined.php
        ├── PelEntryVersion.php
        ├── PelExif.php
        ├── PelIfd.php
        ├── PelJpeg.php
        ├── PelJpegComment.php
        ├── PelJpegContent.php
        ├── PelJpegMarker.php
        ├── PelTag.php
        ├── PelTiff.php
        └── PelException.php
```

### 3. Set Permissions

In Hostinger File Manager, set permissions (chmod):

| Path | Permission |
|------|-----------|
| `public_html/` | 755 |
| `public_html/assets/` | 755 |
| `public_html/lib/` | 755 |
| All `.php` files | 644 |
| All `.css`, `.js` files | 644 |
| `.htaccess` | 644 |

PHP's `sys_get_temp_dir()` is used for temp files — this works automatically on Hostinger shared hosting.

### 4. Verify PHP Extensions

GeoTagr Pro requires these PHP extensions (all standard on Hostinger PHP 8.x):

- `fileinfo` — MIME type detection
- `exif` — reading existing EXIF data
- `zip` — batch ZIP downloads
- `gd` — for PNG/WebP strip mode fallback

Check at: **Hosting → PHP → PHP Configuration → Extensions**

### 5. Test the Application

1. Visit `https://yourdomain.com/` (or the subfolder URL)
2. Upload a JPEG photo
3. Click the map to drop a pin
4. Click "Download Geotagged Photo"
5. Open the downloaded file's properties and verify GPS coordinates are embedded

**Verify with:** [Jeffrey's Exif Viewer](https://exifdata.com/) or ExifTool

---

## Troubleshooting

### "No files were processed successfully"
- Check PHP `upload_max_filesize` in `.htaccess` (default: 50M)
- Ensure PHP version is 8.0+ in Hostinger settings
- Check that `lib/pel/` folder was uploaded with all files

### Geocoding / Search not working
- `geocode.php` proxies Nominatim — requires `allow_url_fopen = On` in PHP settings
- Enable in Hostinger: **Hosting → PHP → PHP Configuration → `allow_url_fopen`**

### Map not loading
- CDN URLs require internet access from client browser
- Leaflet loads from `unpkg.com` — no API key needed

### EXIF not writing (PNG/WebP)
- PNG and WebP use XMP metadata (not EXIF) — verify with an XMP-aware viewer like `exiftool -xmp file.png`

---

## Tech Stack

| Component | Technology |
|-----------|-----------|
| Frontend  | HTML5, Vanilla JS, CSS3 |
| Map       | Leaflet.js + OpenStreetMap |
| Geocoding | Nominatim API (free) |
| Satellite | Esri World Imagery |
| EXIF read | exifr.js (CDN) |
| EXIF write | PHP + PEL library |
| Backend   | PHP 8.x |
| Batch ZIP | PHP ZipArchive |

---

## PEL Library

This project uses the [PHP Exif Library (PEL)](https://github.com/pel/pel) for writing EXIF GPS data to JPEG files. PEL is included in `lib/pel/` and loaded manually without Composer.

---

## Privacy

- Photos are processed server-side in a temporary directory
- Temp files are deleted immediately after download
- No photos are stored, logged, or transmitted to third parties
- Geocoding uses Nominatim (OpenStreetMap) — no API key or account required
