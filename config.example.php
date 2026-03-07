<?php
/**
 * ESP32 PhotoFrame Web – Configuration
 * -------------------------------------------------------
 * Copy this file to config.php and fill in your values.
 * config.php is listed in .gitignore and will never be committed.
 */

// ── Admin login (web interface) ───────────────────────────
define('ADMIN_USER',     'admin');
define('ADMIN_PASSWORD', 'CHANGE_ME_ADMIN_PASSWORD');   // <-- set a strong password

// ── Bearer token (image URL for the frame) ────────────────
// Generate one e.g. with: openssl rand -base64 48
define('FRAME_TOKEN', 'CHANGE_ME_BEARER_TOKEN');        // <-- set a random token

// ── Upload directory ──────────────────────────────────────
define('UPLOAD_DIR', __DIR__ . '/uploads/');

// ── Pin file (next request delivers this image) ───────────
define('PIN_FILE', UPLOAD_DIR . '.pinned');

// ── Target resolution for the frame ──────────────────────
define('FRAME_WIDTH',  800);
define('FRAME_HEIGHT', 480);

// ── Allowed MIME types ────────────────────────────────────
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// ── Harden session ────────────────────────────────────────
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode',  1);
