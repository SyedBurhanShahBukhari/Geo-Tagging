<?php
/**
 * GeoTagr Pro — geocode.php
 * Nominatim proxy to avoid CORS issues
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=3600');

$q   = isset($_GET['q'])   ? trim($_GET['q'])   : '';
$lat = isset($_GET['lat']) ? trim($_GET['lat']) : '';
$lon = isset($_GET['lon']) ? trim($_GET['lon']) : '';

if (!$q && (!$lat || !$lon)) {
    http_response_code(400);
    echo json_encode(['error' => 'Provide either q (search) or lat+lon (reverse geocode)']);
    exit;
}

$userAgent = 'GeoTagrPro/1.0 (photo geotagging tool; contact@geotag.example.com)';

if ($q) {
    // Forward geocoding
    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q'              => $q,
        'format'         => 'json',
        'limit'          => 8,
        'addressdetails' => 1,
    ]);
} else {
    // Reverse geocoding
    $lat = (float)$lat;
    $lon = (float)$lon;

    if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid coordinates']);
        exit;
    }

    $url = 'https://nominatim.openstreetmap.org/reverse?' . http_build_query([
        'lat'    => $lat,
        'lon'    => $lon,
        'format' => 'json',
    ]);
}

$ctx = stream_context_create([
    'http' => [
        'method'          => 'GET',
        'header'          => 'User-Agent: ' . $userAgent . "\r\nAccept: application/json\r\n",
        'timeout'         => 10,
        'ignore_errors'   => true,
    ],
    'ssl' => [
        'verify_peer'      => true,
        'verify_peer_name' => true,
    ],
]);

$response = @file_get_contents($url, false, $ctx);

if ($response === false) {
    // Try with SSL verification disabled as fallback for some shared hosts
    $ctx2 = stream_context_create([
        'http' => [
            'method'        => 'GET',
            'header'        => 'User-Agent: ' . $userAgent . "\r\nAccept: application/json\r\n",
            'timeout'       => 10,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ],
    ]);
    $response = @file_get_contents($url, false, $ctx2);
}

if ($response === false || $response === '') {
    http_response_code(502);
    echo json_encode(['error' => 'Failed to reach Nominatim API']);
    exit;
}

// Validate JSON
$decoded = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(502);
    echo json_encode(['error' => 'Invalid response from Nominatim']);
    exit;
}

echo $response;
