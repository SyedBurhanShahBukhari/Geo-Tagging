<?php
/**
 * GeoTagr Pro — upload.php
 * Handles file upload, EXIF writing, batch processing, and download
 */

error_reporting(0);
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

require_once __DIR__ . '/lib/GeoTagger.php';

// --- Input validation ---
$lat       = isset($_POST['lat'])       ? (float)$_POST['lat']       : null;
$lon       = isset($_POST['lon'])       ? (float)$_POST['lon']       : null;
$alt       = isset($_POST['alt'])       ? (float)$_POST['alt']       : 0.0;
$keywords  = isset($_POST['keywords'])  ? trim($_POST['keywords'])    : '';
$desc      = isset($_POST['description']) ? trim($_POST['description']) : '';
$datetime  = isset($_POST['datetime'])  ? trim($_POST['datetime'])    : '';
$copyright = isset($_POST['copyright']) ? trim($_POST['copyright'])   : '';
$stripExif = isset($_POST['strip_exif']) && $_POST['strip_exif'] === '1';

if (!isset($_FILES['files']) || empty($_FILES['files']['name'][0])) {
    jsonError('No files uploaded');
}

// Validate coordinates (only required if not stripping)
if (!$stripExif && ($lat === null || $lon === null)) {
    jsonError('Latitude and longitude are required');
}
if (!$stripExif && ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180)) {
    jsonError('Invalid coordinates');
}

// Allowed MIME types
$allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];
$allowedExts  = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'];

// Create temp directory
$tmpDir = sys_get_temp_dir() . '/geotag_' . uniqid('', true);
if (!mkdir($tmpDir, 0755, true)) {
    jsonError('Failed to create temp directory');
}

$geoTagger = new GeoTagger();
$processed = [];
$errors    = [];

// Normalize $_FILES['files'] structure
$files = normalizeFilesArray($_FILES['files']);

foreach ($files as $idx => $file) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = $file['name'] . ': upload error ' . $file['error'];
        continue;
    }

    // Validate file type
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mime     = $finfo->file($file['tmp_name']);
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($mime, $allowedMimes) && !in_array($ext, $allowedExts)) {
        $errors[] = $file['name'] . ': unsupported file type';
        continue;
    }

    // Sanitize filename
    $safeName  = preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
    $inputPath = $tmpDir . '/in_' . $idx . '_' . $safeName;
    $outPath   = $tmpDir . '/out_' . $idx . '_' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $inputPath)) {
        $errors[] = $file['name'] . ': failed to save';
        continue;
    }

    $success = true;

    if ($stripExif) {
        $success = $geoTagger->stripExif($inputPath, $outPath);
    } else {
        // Write GPS
        $success = $geoTagger->writeGPS($inputPath, $lat, $lon, $alt, $outPath);

        // Write IPTC/XMP keywords and description
        if ($success && ($keywords || $desc)) {
            // For JPEG: merge IPTC into same file
            if (in_array($mime, ['image/jpeg', 'image/jpg']) || in_array($ext, ['jpg', 'jpeg'])) {
                $geoTagger->writeMeta($outPath, $datetime, $desc, $copyright, $outPath);
            }
        }

        // Write additional EXIF meta (datetime, description, copyright)
        if ($success && ($datetime || $desc || $copyright)) {
            $geoTagger->writeMeta($outPath, $datetime, $desc, $copyright, $outPath);
        }
    }

    if ($success && file_exists($outPath)) {
        $processed[] = [
            'name' => $safeName,
            'path' => $outPath,
            'size' => filesize($outPath),
        ];
    } else {
        // Fallback: serve original
        copy($inputPath, $outPath);
        if (file_exists($outPath)) {
            $processed[] = [
                'name' => $safeName,
                'path' => $outPath,
                'size' => filesize($outPath),
            ];
            $errors[] = $file['name'] . ': EXIF write failed, served original';
        } else {
            $errors[] = $file['name'] . ': processing failed';
        }
    }
}

if (empty($processed)) {
    cleanup($tmpDir);
    jsonError('No files were processed successfully. ' . implode('; ', $errors));
}

// --- Serve response ---

if (count($processed) === 1) {
    // Single file download
    $file = $processed[0];
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file['name'] . '"');
    header('Content-Length: ' . $file['size']);
    header('X-GeoTagr-Errors: ' . json_encode($errors));
    readfile($file['path']);
    cleanup($tmpDir);
    exit;
}

// Multiple files — zip them
if (!class_exists('ZipArchive')) {
    // Fallback: serve first file
    $file = $processed[0];
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $file['name'] . '"');
    header('Content-Length: ' . $file['size']);
    readfile($file['path']);
    cleanup($tmpDir);
    exit;
}

$zipPath = $tmpDir . '/geotagr_batch_' . date('Ymd_His') . '.zip';
$zip     = new ZipArchive();

if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    cleanup($tmpDir);
    jsonError('Failed to create ZIP archive');
}

foreach ($processed as $pf) {
    $zip->addFile($pf['path'], $pf['name']);
}

$zip->close();

if (!file_exists($zipPath)) {
    cleanup($tmpDir);
    jsonError('ZIP creation failed');
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="geotagr_batch.zip"');
header('Content-Length: ' . filesize($zipPath));
header('X-GeoTagr-Errors: ' . json_encode($errors));
readfile($zipPath);
cleanup($tmpDir);
exit;

// --- Helpers ---

function normalizeFilesArray(array $files): array
{
    $normalized = [];
    if (is_array($files['name'])) {
        foreach ($files['name'] as $i => $name) {
            $normalized[] = [
                'name'     => $name,
                'type'     => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ];
        }
    } else {
        $normalized[] = $files;
    }
    return $normalized;
}

function cleanup(string $dir): void
{
    if (!is_dir($dir)) return;
    $items = glob($dir . '/*');
    if ($items) {
        foreach ($items as $item) {
            if (is_file($item)) unlink($item);
        }
    }
    rmdir($dir);
}

function jsonError(string $msg, int $code = 400): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['error' => $msg]);
    exit;
}
