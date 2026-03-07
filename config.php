<?php
/**
 * ESP32 PhotoFrame Web – Configuration
 * -------------------------------------------------------
 * IMPORTANT: This file must NOT be publicly accessible.
 * Change passwords after first setup!
 */

// ── Admin login (web interface) ───────────────────────────
define('ADMIN_USER',     'admin');
define('ADMIN_PASSWORD', 'changeme123');   // <-- change!

// ── Bearer token (image URL for the frame) ────────────────
define('FRAME_TOKEN', 'cHSvovVmMiXwXK6zIw5RHoYhYskeAIl9DGGoEKBMRpPPmkB39xHdaITzMzjjMj2q');       // <-- change!

// ── Upload directory ───────────────────────────────────
define('UPLOAD_DIR', __DIR__ . '/uploads/');

// ── Pin file (next request delivers this image) ──────
define('PIN_FILE', UPLOAD_DIR . '.pinned');

// ── Target resolution for the frame ────────────────────
define('FRAME_WIDTH',  800);
define('FRAME_HEIGHT', 480);

// ── Allowed MIME types ────────────────────────────────
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// ── Harden session ────────────────────────────────────
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode',  1);
