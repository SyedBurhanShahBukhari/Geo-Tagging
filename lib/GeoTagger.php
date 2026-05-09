<?php
/**
 * GeoTagger - EXIF/XMP GPS metadata writer using PEL library
 * Requires PEL source files in the same directory (lib/pel/)
 */

// Load PEL library files manually (no Composer)
$pelDir = __DIR__ . '/pel/';
require_once $pelDir . 'PelException.php';
require_once $pelDir . 'PelConvert.php';
require_once $pelDir . 'PelDataWindow.php';
require_once $pelDir . 'PelJpegMarker.php';
require_once $pelDir . 'PelJpegContent.php';
require_once $pelDir . 'PelJpegComment.php';
require_once $pelDir . 'PelJpeg.php';
require_once $pelDir . 'PelTag.php';
require_once $pelDir . 'PelEntry.php';
require_once $pelDir . 'PelEntryNumber.php';
require_once $pelDir . 'PelEntryByte.php';
require_once $pelDir . 'PelEntryShort.php';
require_once $pelDir . 'PelEntryLong.php';
require_once $pelDir . 'PelEntryRational.php';
require_once $pelDir . 'PelEntryAscii.php';
require_once $pelDir . 'PelEntryUndefined.php';
require_once $pelDir . 'PelEntryVersion.php';
require_once $pelDir . 'PelExif.php';
require_once $pelDir . 'PelIfd.php';
require_once $pelDir . 'PelTiff.php';

use lsolesen\pel\PelJpeg;
use lsolesen\pel\PelExif;
use lsolesen\pel\PelTiff;
use lsolesen\pel\PelIfd;
use lsolesen\pel\PelTag;
use lsolesen\pel\PelEntryAscii;
use lsolesen\pel\PelEntryByte;
use lsolesen\pel\PelEntryRational;
use lsolesen\pel\PelEntryShort;
use lsolesen\pel\PelEntryLong;
use lsolesen\pel\PelEntryUndefined;
use lsolesen\pel\PelConvert;
use lsolesen\pel\PelDataWindow;

class GeoTagger
{
    /**
     * Convert decimal degrees to rational array [degrees, minutes, seconds] as [numerator, denominator]
     */
    private function decimalToRationals(float $decimal): array
    {
        $decimal = abs($decimal);
        $degrees = (int)floor($decimal);
        $minutesFloat = ($decimal - $degrees) * 60;
        $minutes = (int)floor($minutesFloat);
        $secondsFloat = ($minutesFloat - $minutes) * 60;
        // Store seconds with 100x precision
        $secondsNum = (int)round($secondsFloat * 100);

        return [
            [$degrees, 1],
            [$minutes, 1],
            [$secondsNum, 100],
        ];
    }

    /**
     * Write GPS coordinates and optional SEO metadata to an image file
     */
    public function writeGPS(
        string $filePath,
        float  $lat,
        float  $lon,
        float  $alt         = 0.0,
        string $outputPath  = '',
        string $description = '',
        string $keywords    = '',
        string $copyright   = ''
    ): bool {
        if ($outputPath === '') {
            $outputPath = $filePath;
        }

        $mime = $this->getMimeType($filePath);

        if ($mime === 'image/jpeg') {
            return $this->writeGpsJpeg($filePath, $lat, $lon, $alt, $outputPath, $description, $keywords, $copyright);
        } elseif ($mime === 'image/png') {
            return $this->writeXmpFile($filePath, $lat, $lon, $alt, $outputPath, 'png', $description, $keywords, $copyright);
        } elseif ($mime === 'image/webp') {
            return $this->writeXmpFile($filePath, $lat, $lon, $alt, $outputPath, 'webp', $description, $keywords, $copyright);
        } else {
            // HEIC and others — attempt JPEG-style
            return $this->writeGpsJpeg($filePath, $lat, $lon, $alt, $outputPath, $description, $keywords, $copyright);
        }
    }

    /**
     * Write GPS (and SEO metadata) to JPEG using PEL, then inject XMP for SEO fields
     */
    private function writeGpsJpeg(
        string $filePath,
        float  $lat,
        float  $lon,
        float  $alt,
        string $outputPath,
        string $description = '',
        string $keywords    = '',
        string $copyright   = ''
    ): bool {
        try {
            $jpeg = new PelJpeg($filePath);
            $exif = $jpeg->getExif();

            if ($exif === null) {
                $exif = new PelExif();
                $jpeg->setExif($exif);
                $tiff = new PelTiff();
                $exif->setTiff($tiff);
            } else {
                $tiff = $exif->getTiff();
                if ($tiff === null) {
                    $tiff = new PelTiff();
                    $exif->setTiff($tiff);
                }
            }

            $ifd0 = $tiff->getIfd();
            if ($ifd0 === null) {
                $ifd0 = new PelIfd(PelIfd::IFD0);
                $tiff->setIfd($ifd0);
            }

            // GPS IFD
            $gpsIfd = new PelIfd(PelIfd::GPS);
            $gpsIfd->addEntry(new PelEntryByte(PelTag::GPS_VERSION_ID, 2, 3, 0, 0));

            $latRef  = $lat >= 0 ? 'N' : 'S';
            $latRats = $this->decimalToRationals($lat);
            $gpsIfd->addEntry(new PelEntryAscii(PelTag::GPS_LATITUDE_REF, $latRef));
            $gpsIfd->addEntry(new PelEntryRational(PelTag::GPS_LATITUDE,
                $latRats[0], $latRats[1], $latRats[2]));

            $lonRef  = $lon >= 0 ? 'E' : 'W';
            $lonRats = $this->decimalToRationals($lon);
            $gpsIfd->addEntry(new PelEntryAscii(PelTag::GPS_LONGITUDE_REF, $lonRef));
            $gpsIfd->addEntry(new PelEntryRational(PelTag::GPS_LONGITUDE,
                $lonRats[0], $lonRats[1], $lonRats[2]));

            $altRef = $alt >= 0 ? 0 : 1;
            $altNum = (int)round(abs($alt) * 100);
            $gpsIfd->addEntry(new PelEntryByte(PelTag::GPS_ALTITUDE_REF, $altRef));
            $gpsIfd->addEntry(new PelEntryRational(PelTag::GPS_ALTITUDE, [$altNum, 100]));

            $ifd0->addSubIfd($gpsIfd);

            // EXIF description and copyright in IFD0
            if ($description) {
                $ifd0->addEntry(new PelEntryAscii(PelTag::IMAGE_DESCRIPTION, $description));
            }
            if ($copyright) {
                $ifd0->addEntry(new PelEntryAscii(PelTag::COPYRIGHT, $copyright));
            }

            file_put_contents($outputPath, $jpeg->getBytes());
        } catch (\Exception $e) {
            // PEL failed — fall back to binary GPS injection
            $ok = $this->writeGpsBinaryFallback($filePath, $lat, $lon, $alt, $outputPath);
            if (!$ok) return false;
        }

        // Always inject XMP APP1 for keywords + full metadata (works regardless of PEL)
        if ($description || $keywords || $copyright) {
            $xmp = $this->buildXmpGps($lat, $lon, $alt, '', $description, $keywords, $copyright);
            $this->injectXmpJpeg($outputPath, $xmp, $outputPath);
        }

        return true;
    }

    /**
     * Binary fallback GPS writer for JPEG (no PEL)
     * Injects a minimal EXIF GPS block if PEL is unavailable
     */
    private function writeGpsBinaryFallback(string $filePath, float $lat, float $lon, float $alt, string $outputPath): bool
    {
        $data = file_get_contents($filePath);
        if ($data === false) return false;

        // Build GPS EXIF APP1 segment
        $gpsExif = $this->buildGpsExifSegment($lat, $lon, $alt);

        // Remove existing APP1 if present, insert new one after SOI
        $soi = "\xFF\xD8";
        if (substr($data, 0, 2) !== $soi) return false;

        $rest = substr($data, 2);

        // Strip existing APP1 (EXIF) markers to avoid duplication
        while (strlen($rest) > 4 && substr($rest, 0, 2) === "\xFF\xE1") {
            $segLen = unpack('n', substr($rest, 2, 2))[1];
            $rest = substr($rest, 2 + $segLen);
        }

        $result = $soi . $gpsExif . $rest;
        return file_put_contents($outputPath, $result) !== false;
    }

    /**
     * Build a minimal EXIF APP1 segment with GPS IFD
     */
    private function buildGpsExifSegment(float $lat, float $lon, float $alt): string
    {
        $latRef  = $lat >= 0 ? 'N' : 'S';
        $lonRef  = $lon >= 0 ? 'E' : 'W';
        $altRef  = $alt >= 0 ? 0 : 1;

        $latRats = $this->decimalToRationals($lat);
        $lonRats = $this->decimalToRationals($lon);
        $altNum  = (int)round(abs($alt) * 100);

        // TIFF header (little-endian): II + 42 + offset to IFD0
        // IFD0 has 1 entry: GPS IFD pointer (tag 0x8825)
        // GPS IFD has entries for lat/lon/alt

        // We'll build the GPS IFD
        $gpsEntries = [];

        // GPSVersionID (BYTE[4], tag 0x0000)
        $gpsEntries[] = $this->ifdEntry(0x0000, 1, 4, "\x02\x03\x00\x00");

        // GPSLatitudeRef (ASCII 2, tag 0x0001)
        $gpsEntries[] = $this->ifdEntry(0x0001, 2, 2, $latRef . "\x00");

        // GPSLatitude (RATIONAL[3], tag 0x0002) — data goes in extra data section
        $latData  = $this->packRationals($latRats);
        $gpsEntries[] = ['tag' => 0x0002, 'type' => 5, 'count' => 3, 'data' => $latData];

        // GPSLongitudeRef (ASCII 2, tag 0x0003)
        $gpsEntries[] = $this->ifdEntry(0x0003, 2, 2, $lonRef . "\x00");

        // GPSLongitude (RATIONAL[3], tag 0x0004)
        $lonData = $this->packRationals($lonRats);
        $gpsEntries[] = ['tag' => 0x0004, 'type' => 5, 'count' => 3, 'data' => $lonData];

        // GPSAltitudeRef (BYTE[1], tag 0x0005)
        $gpsEntries[] = $this->ifdEntry(0x0005, 1, 1, chr($altRef));

        // GPSAltitude (RATIONAL[1], tag 0x0006)
        $altData = pack('VV', $altNum, 100);
        $gpsEntries[] = ['tag' => 0x0006, 'type' => 5, 'count' => 1, 'data' => $altData];

        // Assemble GPS IFD
        $numEntries = count($gpsEntries);
        // IFD0 starts at offset 8 (right after TIFF header)
        // IFD0 has 1 entry (GPS pointer) = 2 + 12 + 4 = 18 bytes
        // GPS IFD pointer entry value = offset to GPS IFD
        // GPS IFD starts at 8 + 18 = 26

        $ifd0Offset   = 8;
        $ifd0Size     = 2 + (1 * 12) + 4; // 1 entry in IFD0
        $gpsIfdOffset = $ifd0Offset + $ifd0Size; // = 26

        // Calculate extra data offset for GPS IFD entries
        $gpsIfdHeaderSize = 2 + ($numEntries * 12) + 4;
        $gpsExtraOffset   = $gpsIfdOffset + $gpsIfdHeaderSize;

        // Build GPS IFD entries bytes
        $gpsIfdBytes  = pack('v', $numEntries);
        $gpsExtraData = '';

        foreach ($gpsEntries as $e) {
            if (isset($e['data'])) {
                // Has external data
                $dataLen = strlen($e['data']);
                $offset  = $gpsExtraOffset + strlen($gpsExtraData);
                $gpsIfdBytes  .= pack('vvV', $e['tag'], $e['type'], $e['count']);
                $gpsIfdBytes  .= pack('V', $offset);
                $gpsExtraData .= $e['data'];
            } else {
                // Inline data (padded to 4 bytes)
                $inline = str_pad($e['value'], 4, "\x00");
                $gpsIfdBytes .= pack('vvV', $e['tag'], $e['type'], $e['count']);
                $gpsIfdBytes .= substr($inline, 0, 4);
            }
        }
        $gpsIfdBytes .= pack('V', 0); // Next IFD offset = 0

        // Build IFD0 with GPS sub-IFD entry (tag 0x8825)
        $gpsIfdPointerValue = pack('V', $gpsIfdOffset);
        $ifd0Bytes  = pack('v', 1); // 1 entry
        $ifd0Bytes .= pack('vvV', 0x8825, 4, 1); // tag, type LONG, count 1
        $ifd0Bytes .= pack('V', $gpsIfdOffset);   // value = GPS IFD offset
        $ifd0Bytes .= pack('V', 0);               // next IFD = 0

        // TIFF header (little-endian)
        $tiffHeader  = "II";           // byte order: little-endian
        $tiffHeader .= pack('v', 42);  // TIFF magic
        $tiffHeader .= pack('V', $ifd0Offset); // IFD0 offset

        $tiffData = $tiffHeader . $ifd0Bytes . $gpsIfdBytes . $gpsExtraData;

        // APP1 segment: marker + length (2 bytes, includes length field) + "Exif\0\0" + TIFF
        $exifHeader = "Exif\x00\x00";
        $payload    = $exifHeader . $tiffData;
        $segLen     = strlen($payload) + 2; // +2 for the length field itself
        $app1       = "\xFF\xE1" . pack('n', $segLen) . $payload;

        return $app1;
    }

    private function ifdEntry(int $tag, int $type, int $count, string $value): array
    {
        return ['tag' => $tag, 'type' => $type, 'count' => $count, 'value' => $value];
    }

    private function packRationals(array $rats): string
    {
        $bytes = '';
        foreach ($rats as $r) {
            $bytes .= pack('VV', $r[0], $r[1]);
        }
        return $bytes;
    }

    /**
     * Inject XMP APP1 segment into a JPEG file (separate from EXIF APP1)
     */
    private function injectXmpJpeg(string $filePath, string $xmp, string $outputPath): bool
    {
        $data = file_get_contents($filePath);
        if ($data === false || substr($data, 0, 2) !== "\xFF\xD8") return false;

        // XMP APP1 uses the Adobe XMP namespace as identifier instead of "Exif\0\0"
        $xmpNs   = "http://ns.adobe.com/xap/1.0/\x00";
        $payload = $xmpNs . $xmp;
        $segLen  = strlen($payload) + 2;
        $app1    = "\xFF\xE1" . pack('n', $segLen) . $payload;

        // Insert after SOI + any existing EXIF APP1, before other segments
        $pos = 2;
        while ($pos + 4 <= strlen($data)) {
            if ($data[$pos] !== "\xFF") break;
            $marker = ord($data[$pos + 1]);
            if ($marker === 0xE1) { // APP1
                $slen = unpack('n', substr($data, $pos + 2, 2))[1];
                // Skip existing XMP APP1 (replace it)
                if (substr($data, $pos + 4, 29) === "http://ns.adobe.com/xap/1.0/\x00") {
                    $data = substr($data, 0, $pos) . substr($data, $pos + 2 + $slen);
                    continue;
                }
                $pos += 2 + $slen;
            } elseif ($marker === 0xE0) { // APP0 — skip JFIF
                $slen = unpack('n', substr($data, $pos + 2, 2))[1];
                $pos += 2 + $slen;
            } else {
                break;
            }
        }

        $result = substr($data, 0, $pos) . $app1 . substr($data, $pos);
        return file_put_contents($outputPath, $result) !== false;
    }

    /**
     * Write XMP metadata (GPS + SEO) to PNG or WebP files
     */
    private function writeXmpFile(
        string $filePath,
        float  $lat,
        float  $lon,
        float  $alt,
        string $outputPath,
        string $type,
        string $description = '',
        string $keywords    = '',
        string $copyright   = ''
    ): bool {
        $xmp = $this->buildXmpGps($lat, $lon, $alt, '', $description, $keywords, $copyright);

        if ($type === 'png') {
            return $this->injectXmpPng($filePath, $xmp, $outputPath);
        } elseif ($type === 'webp') {
            return $this->injectXmpWebP($filePath, $xmp, $outputPath);
        }
        return false;
    }

    /**
     * Build XMP GPS packet
     */
    private function buildXmpGps(float $lat, float $lon, float $alt, string $datetime = '', string $description = '', string $keywords = '', string $copyright = ''): string
    {
        $latRef = $lat >= 0 ? 'N' : 'S';
        $lonRef = $lon >= 0 ? 'E' : 'W';

        $latAbs = abs($lat);
        $lonAbs = abs($lon);

        // Convert to DMS strings for XMP
        $latDeg = (int)floor($latAbs);
        $latMin = (int)floor(($latAbs - $latDeg) * 60);
        $latSec = (($latAbs - $latDeg) * 60 - $latMin) * 60;
        $latXmp = sprintf('%d,%d,%sF%s', $latDeg, $latMin, number_format($latSec, 4, '.', ''), $latRef);

        $lonDeg = (int)floor($lonAbs);
        $lonMin = (int)floor(($lonAbs - $lonDeg) * 60);
        $lonSec = (($lonAbs - $lonDeg) * 60 - $lonMin) * 60;
        $lonXmp = sprintf('%d,%d,%sF%s', $lonDeg, $lonMin, number_format($lonSec, 4, '.', ''), $lonRef);

        $altStr = number_format(abs($alt), 2, '.', '');

        $descTag     = $description ? '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description) . '</rdf:li></rdf:Alt></dc:description>' : '';
        $kwTag       = '';
        if ($keywords) {
            $kwList = array_map('trim', explode(',', $keywords));
            $kwItems = implode('', array_map(fn($k) => '<rdf:li>' . htmlspecialchars($k) . '</rdf:li>', $kwList));
            $kwTag   = '<dc:subject><rdf:Bag>' . $kwItems . '</rdf:Bag></dc:subject>';
        }
        $crTag       = $copyright ? '<dc:rights><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($copyright) . '</rdf:li></rdf:Alt></dc:rights>' : '';
        $dtTag       = $datetime  ? '<xmp:CreateDate>' . htmlspecialchars($datetime) . '</xmp:CreateDate>' : '';

        return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>' .
            '<x:xmpmeta xmlns:x="adobe:ns:meta/">' .
            '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">' .
            '<rdf:Description rdf:about=""' .
            ' xmlns:exif="http://ns.adobe.com/exif/1.0/"' .
            ' xmlns:dc="http://purl.org/dc/elements/1.1/"' .
            ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">' .
            '<exif:GPSLatitude>'  . $latXmp . '</exif:GPSLatitude>' .
            '<exif:GPSLongitude>' . $lonXmp . '</exif:GPSLongitude>' .
            '<exif:GPSAltitude>'  . $altStr . '</exif:GPSAltitude>' .
            '<exif:GPSAltitudeRef>' . ($alt >= 0 ? '0' : '1') . '</exif:GPSAltitudeRef>' .
            $descTag . $kwTag . $crTag . $dtTag .
            '</rdf:Description>' .
            '</rdf:RDF>' .
            '</x:xmpmeta>' .
            '<?xpacket end="w"?>';
    }

    /**
     * Inject XMP into PNG as iTXt chunk
     */
    private function injectXmpPng(string $filePath, string $xmp, string $outputPath): bool
    {
        $data = file_get_contents($filePath);
        if ($data === false || substr($data, 0, 8) !== "\x89PNG\r\n\x1a\n") return false;

        // Build iTXt chunk: keyword=XML:com.adobe.xmp + xmp data
        $keyword  = "XML:com.adobe.xmp";
        $chunkData = $keyword . "\x00\x00\x00\x00\x00" . $xmp;
        $chunkLen  = strlen($chunkData);
        $chunkType = "iTXt";
        $crc       = crc32($chunkType . $chunkData) & 0xFFFFFFFF;
        $chunk     = pack('N', $chunkLen) . $chunkType . $chunkData . pack('N', $crc);

        // Insert after PNG signature + IHDR chunk
        $ihdrEnd = 8 + 4 + 4 + 13 + 4; // sig + len + type + data + crc
        $result  = substr($data, 0, $ihdrEnd) . $chunk . substr($data, $ihdrEnd);

        return file_put_contents($outputPath, $result) !== false;
    }

    /**
     * Inject XMP into WebP as XMP  chunk
     */
    private function injectXmpWebP(string $filePath, string $xmp, string $outputPath): bool
    {
        $data = file_get_contents($filePath);
        if ($data === false) return false;

        // Verify RIFF header
        if (substr($data, 0, 4) !== 'RIFF' || substr($data, 8, 4) !== 'WEBP') return false;

        // Build XMP  chunk (note trailing space in "XMP ")
        $xmpPadded = strlen($xmp) % 2 === 1 ? $xmp . "\x00" : $xmp;
        $xmpChunk  = 'XMP ' . pack('V', strlen($xmp)) . $xmpPadded;

        // Append XMP chunk and update RIFF size
        $riffSize  = unpack('V', substr($data, 4, 4))[1];
        $newData   = substr($data, 0, 12) . substr($data, 12) . $xmpChunk;
        $newSize   = strlen($newData) - 8;
        $newData   = substr($newData, 0, 4) . pack('V', $newSize) . substr($newData, 8);

        // Set Extended flag in VP8X if needed
        // For simplicity, just append; most parsers will find it
        return file_put_contents($outputPath, $newData) !== false;
    }

    /**
     * Read GPS data from a JPEG file
     */
    public function readGPS(string $filePath): ?array
    {
        // Try PHP's built-in exif reader first
        $exif = @exif_read_data($filePath, 'GPS,EXIF,IFD0', true);
        if (!$exif) return null;

        $gps = $exif['GPS'] ?? null;
        if (!$gps) return null;

        $lat = $this->gpsToDecimal($gps, 'Latitude');
        $lon = $this->gpsToDecimal($gps, 'Longitude');
        if ($lat === null || $lon === null) return null;

        $result = [
            'lat' => $lat,
            'lon' => $lon,
        ];

        if (isset($gps['GPSAltitude'])) {
            [$num, $den] = explode('/', $gps['GPSAltitude']);
            $alt = $den != 0 ? (float)$num / (float)$den : 0.0;
            $result['alt'] = isset($gps['GPSAltitudeRef']) && $gps['GPSAltitudeRef'] === "\x01" ? -$alt : $alt;
        }

        if (isset($exif['EXIF']['DateTimeOriginal'])) {
            $result['datetime'] = $exif['EXIF']['DateTimeOriginal'];
        }

        return $result;
    }

    private function gpsToDecimal(array $gps, string $which): ?float
    {
        $refKey    = 'GPS' . $which . 'Ref';
        $coordKey  = 'GPS' . $which;

        if (!isset($gps[$refKey], $gps[$coordKey])) return null;

        $parts = $gps[$coordKey];
        if (!is_array($parts) || count($parts) < 3) return null;

        $decimal = 0.0;
        $divisor = 1.0;
        foreach ($parts as $part) {
            [$num, $den] = explode('/', $part);
            $decimal += ((float)$num / (float)$den) / $divisor;
            $divisor  *= 60;
        }

        $ref = strtoupper($gps[$refKey]);
        if ($ref === 'S' || $ref === 'W') $decimal = -$decimal;

        return $decimal;
    }

    /**
     * Write optional metadata: DateTimeOriginal, ImageDescription, Copyright
     */
    public function writeMeta(string $filePath, string $datetime, string $description, string $copyright, string $outputPath): bool
    {
        $mime = $this->getMimeType($filePath);
        if ($mime !== 'image/jpeg') {
            // For non-JPEG, just copy the file — XMP is handled by writeGPS
            if ($filePath !== $outputPath) copy($filePath, $outputPath);
            return true;
        }

        try {
            $jpeg = new PelJpeg($filePath);
            $exif = $jpeg->getExif();

            if ($exif === null) {
                $exif = new PelExif();
                $jpeg->setExif($exif);
                $tiff = new PelTiff();
                $exif->setTiff($tiff);
            } else {
                $tiff = $exif->getTiff();
                if ($tiff === null) {
                    $tiff = new PelTiff();
                    $exif->setTiff($tiff);
                }
            }

            $ifd0 = $tiff->getIfd();
            if ($ifd0 === null) {
                $ifd0 = new PelIfd(PelIfd::IFD0);
                $tiff->setIfd($ifd0);
            }

            if ($description) {
                $ifd0->addEntry(new PelEntryAscii(PelTag::IMAGE_DESCRIPTION, $description));
            }
            if ($copyright) {
                $ifd0->addEntry(new PelEntryAscii(PelTag::COPYRIGHT, $copyright));
            }

            if ($datetime) {
                $exifIfd = $ifd0->getSubIfd(PelIfd::EXIF);
                if ($exifIfd === null) {
                    $exifIfd = new PelIfd(PelIfd::EXIF);
                    $ifd0->addSubIfd($exifIfd);
                }
                // Format: "YYYY:MM:DD HH:MM:SS"
                $dt = str_replace(['T', '-'], [':', ':'], $datetime);
                // Reformat properly
                if (strlen($datetime) >= 16) {
                    $dt = date('Y:m:d H:i:s', strtotime($datetime));
                }
                $exifIfd->addEntry(new PelEntryAscii(PelTag::DATE_TIME_ORIGINAL, $dt));
            }

            file_put_contents($outputPath, $jpeg->getBytes());
            return true;
        } catch (\Exception $e) {
            if ($filePath !== $outputPath) copy($filePath, $outputPath);
            return false;
        }
    }

    /**
     * Write IPTC keywords (embedded in JPEG APP13)
     */
    public function writeIPTC(string $filePath, string $keywords, string $description, string $outputPath = ''): bool
    {
        if ($outputPath === '') $outputPath = $filePath;

        // IPTC in JPEG is complex; embed as XMP for simplicity and compatibility
        $xmp = $this->buildXmpGps(0, 0, 0, '', $description, $keywords);

        $data = file_get_contents($filePath);
        if ($data === false) return false;

        // Build XMP APP1 segment
        $xmpNs  = "http://ns.adobe.com/xap/1.0/\x00";
        $payload = $xmpNs . $xmp;
        $segLen  = strlen($payload) + 2;
        $app1    = "\xFF\xE1" . pack('n', $segLen) . $payload;

        // Insert after SOI
        if (substr($data, 0, 2) !== "\xFF\xD8") return false;
        $result = "\xFF\xD8" . $app1 . substr($data, 2);

        return file_put_contents($outputPath, $result) !== false;
    }

    /**
     * Strip all EXIF / metadata from a JPEG
     */
    public function stripExif(string $filePath, string $outputPath): bool
    {
        $mime = $this->getMimeType($filePath);

        if ($mime === 'image/jpeg') {
            return $this->stripExifJpeg($filePath, $outputPath);
        } elseif ($mime === 'image/png') {
            // Re-encode PNG without metadata chunks
            $img = imagecreatefrompng($filePath);
            if (!$img) return false;
            imagepng($img, $outputPath, 9);
            imagedestroy($img);
            return true;
        } elseif ($mime === 'image/webp') {
            $img = imagecreatefromwebp($filePath);
            if (!$img) return false;
            imagewebp($img, $outputPath, 90);
            imagedestroy($img);
            return true;
        }

        return copy($filePath, $outputPath);
    }

    /**
     * Strip EXIF from JPEG by removing APP0/APP1/APP13 segments
     */
    private function stripExifJpeg(string $filePath, string $outputPath): bool
    {
        $data = file_get_contents($filePath);
        if ($data === false || substr($data, 0, 2) !== "\xFF\xD8") return false;

        $result = "\xFF\xD8";
        $i      = 2;
        $len    = strlen($data);

        while ($i < $len - 1) {
            if ($data[$i] !== "\xFF") break;
            $marker = ord($data[$i + 1]);

            if ($marker === 0xD9) { // EOI
                $result .= "\xFF\xD9";
                break;
            }

            if ($marker === 0xDA) { // SOS — rest is image data
                $result .= substr($data, $i);
                break;
            }

            // Segments with length
            if ($i + 3 >= $len) break;
            $segLen = unpack('n', substr($data, $i + 2, 2))[1];

            // Skip APP0–APP15 (0xE0–0xEF) = metadata
            if ($marker >= 0xE0 && $marker <= 0xEF) {
                $i += 2 + $segLen;
                continue;
            }

            // Keep everything else
            $result .= substr($data, $i, 2 + $segLen);
            $i += 2 + $segLen;
        }

        return file_put_contents($outputPath, $result) !== false;
    }

    private function getMimeType(string $filePath): string
    {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($filePath);
        return $mime ?: 'application/octet-stream';
    }
}
