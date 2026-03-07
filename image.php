<?php
/**
 * image.php – Returns a random uploaded image.
 *
 * Protected via Bearer Token: Authorization: Bearer <token>
 * Called directly by the ESP32 photo frame.
 */
require_once __DIR__ . '/config.php';

/* ─────────────────────────────────────────────────────────
 *  Bearer auth
 * ───────────────────────────────────────────────────────── */
$authHeader = $_SERVER['HTTP_AUTHORIZATION']
           ?? apache_request_headers()['Authorization']
           ?? '';

if (!preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $m) || $m[1] !== FRAME_TOKEN) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Bearer realm="ESP32 PhotoFrame Web"');
    echo '401 Unauthorized';
    exit;
}

/* ─────────────────────────────────────────────────────────
 *  Pick image
 * ───────────────────────────────────────────────────────── */
$images = glob(UPLOAD_DIR . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) ?: [];

if (empty($images)) {
    // Generate placeholder image when nothing has been uploaded yet
    header('Content-Type: image/jpeg');
    $img = imagecreatetruecolor(FRAME_WIDTH, FRAME_HEIGHT);
    $bg  = imagecolorallocate($img, 15, 15, 18);
    $fg  = imagecolorallocate($img, 108, 99, 255);
    imagefill($img, 0, 0, $bg);
    $text = 'No images uploaded yet';
    // Zentriert schreiben (built-in font 5)
    $fw = imagefontwidth(5)  * strlen($text);
    $fh = imagefontheight(5);
    $x  = (int)(FRAME_WIDTH  / 2 - $fw / 2);
    $y  = (int)(FRAME_HEIGHT / 2 - $fh / 2);
    imagestring($img, 5, $x, $y, $text, $fg);
    imagejpeg($img, null, 90);
    imagedestroy($img);
    exit;
}

// Pinned image has priority – delivered once, then pin is removed
$chosen = null;
if (is_file(PIN_FILE)) {
    $pinned = trim(file_get_contents(PIN_FILE));
    $pinnedPath = realpath(UPLOAD_DIR . $pinned);
    if ($pinnedPath && in_array($pinnedPath, array_map('realpath', $images))) {
        $chosen = $pinnedPath;
    }
    unlink(PIN_FILE); // always delete, even if file no longer exists
}

// Fallback: random
if (!$chosen) {
    $chosen = $images[random_int(0, count($images) - 1)];
}

/* ─────────────────────────────────────────────────────────
 *  Serve file
 * ───────────────────────────────────────────────────────── */

// Security check: file must be inside UPLOAD_DIR
$real = realpath($chosen);
if ($real === false || strpos($real, realpath(UPLOAD_DIR)) !== 0) {
    http_response_code(403);
    exit('403 Forbidden');
}

$mime = mime_content_type($real);
$size = filesize($real);

// Cache headers – frame must not cache images
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: '   . $mime);
header('Content-Length: ' . $size);
header('X-Frame-File: '   . basename($real)); // optional, for diagnostics

readfile($real);
