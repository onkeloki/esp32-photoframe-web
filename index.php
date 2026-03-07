<?php
require_once __DIR__ . '/config.php';
session_start();

/* ─────────────────────────────────────────────────────────
 *  Helper functions
 * ───────────────────────────────────────────────────────── */

function isLoggedIn(): bool {
    return !empty($_SESSION['logged_in']);
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function getImages(): array {
    $files = glob(UPLOAD_DIR . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) ?: [];
    usort($files, fn($a, $b) => filemtime($b) - filemtime($a));
    return $files;
}

/**
 * Scale image to FRAME_WIDTH × FRAME_HEIGHT (cover – no black bars,
 * centred crop) and save as JPEG.
 */
function resizeAndSave(string $srcPath, string $destPath, string $mimeType): bool {
    switch ($mimeType) {
        case 'image/jpeg': $src = @imagecreatefromjpeg($srcPath); break;
        case 'image/png':  $src = @imagecreatefrompng($srcPath);  break;
        case 'image/gif':  $src = @imagecreatefromgif($srcPath);  break;
        case 'image/webp': $src = @imagecreatefromwebp($srcPath); break;
        default: return false;
    }
    if (!$src) return false;

    $srcW = imagesx($src);
    $srcH = imagesy($src);

    $dst = imagecreatetruecolor(FRAME_WIDTH, FRAME_HEIGHT);

    // Cover mode: keep aspect ratio, fill frame completely,
    // crop overhanging edges from centre – no black bars.
    $ratioSrc = $srcW / $srcH;
    $ratioDst = FRAME_WIDTH / FRAME_HEIGHT;

    if ($ratioSrc > $ratioDst) {
        // Image wider than frame → scale to height, crop left/right
        $srcCropH = $srcH;
        $srcCropW = (int) round($srcH * $ratioDst);
        $srcX     = (int) round(($srcW - $srcCropW) / 2);
        $srcY     = 0;
    } else {
        // Image taller than frame → scale to width, crop top/bottom
        $srcCropW = $srcW;
        $srcCropH = (int) round($srcW / $ratioDst);
        $srcX     = 0;
        $srcY     = (int) round(($srcH - $srcCropH) / 2);
    }

    imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, FRAME_WIDTH, FRAME_HEIGHT, $srcCropW, $srcCropH);

    $ok = imagejpeg($dst, $destPath, 90);
    imagedestroy($src);
    imagedestroy($dst);
    return $ok;
}

/* ─────────────────────────────────────────────────────────
 *  POST handler
 * ───────────────────────────────────────────────────────── */

$error   = '';
$success = '';

// Login
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';
    if ($user === ADMIN_USER && $pass === ADMIN_PASSWORD) {
        session_regenerate_id(true);
        $_SESSION['logged_in'] = true;
        redirect('index.php');
    }
    $error = 'Wrong username or password.';
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    redirect('index.php');
}

if (isLoggedIn()) {

    // Upload (multi)
    if (isset($_POST['action']) && $_POST['action'] === 'upload') {
        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0750, true);
        }
        $codes = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE in form.',
            UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file selected.',
            UPLOAD_ERR_NO_TMP_DIR => 'Temporary directory missing.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write to disk.',
        ];

        // Normalise $_FILES['photos'] multi-upload array
        $uploads = [];
        if (!empty($_FILES['photos']['name']) && is_array($_FILES['photos']['name'])) {
            foreach ($_FILES['photos']['name'] as $i => $name) {
                $uploads[] = [
                    'name'     => $name,
                    'tmp_name' => $_FILES['photos']['tmp_name'][$i],
                    'error'    => $_FILES['photos']['error'][$i],
                ];
            }
        }

        $okCount  = 0;
        $errLines = [];
        foreach ($uploads as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errLines[] = h($file['name']) . ': ' . ($codes[$file['error']] ?? 'Error ' . $file['error']);
                continue;
            }
            $mime = mime_content_type($file['tmp_name']);
            if (!in_array($mime, ALLOWED_TYPES, true)) {
                $errLines[] = h($file['name']) . ': File type not allowed (' . h($mime) . ')';
                continue;
            }
            $dest = UPLOAD_DIR . uniqid('img_', true) . '.jpg';
            if (resizeAndSave($file['tmp_name'], $dest, $mime)) {
                $okCount++;
            } else {
                $errLines[] = h($file['name']) . ': GD error while processing.';
            }
        }

        if ($okCount)   $success = $okCount . ' image' . ($okCount > 1 ? 's' : '') . ' uploaded and scaled to ' . FRAME_WIDTH . '×' . FRAME_HEIGHT . ' px.';
        if ($errLines)  $error   = implode('<br>', $errLines);
        if (!$okCount && !$errLines) $error = 'No file selected.';
    }

    // Pin: set / remove
    if (isset($_POST['action']) && $_POST['action'] === 'pin') {
        $target = basename($_POST['file'] ?? '');
        $path   = UPLOAD_DIR . $target;
        if ($target && is_file($path) && strpos(realpath($path), realpath(UPLOAD_DIR)) === 0) {
            // Toggle: already pinned → remove, otherwise set
            $current = is_file(PIN_FILE) ? trim(file_get_contents(PIN_FILE)) : '';
            if ($current === $target) {
                unlink(PIN_FILE);
                $success = 'Pin removed – random image selection resumed.';
            } else {
                file_put_contents(PIN_FILE, $target);
                $success = 'Image "' . h($target) . '" pinned – will be shown on next request.';
            }
        } else {
            $error = 'File not found.';
        }
    }

    // Delete
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $target = basename($_POST['file'] ?? '');
        $path   = UPLOAD_DIR . $target;
        if ($target && is_file($path) && strpos(realpath($path), realpath(UPLOAD_DIR)) === 0) {
            unlink($path);
            // Also clean pin file if this image was pinned
            if (is_file(PIN_FILE) && trim(file_get_contents(PIN_FILE)) === $target) {
                unlink(PIN_FILE);
            }
            $success = 'Image "' . h($target) . '" deleted.';
        } else {
            $error = 'File not found or invalid path.';
        }
    }
}

$images   = isLoggedIn() ? getImages() : [];
$pinnedImg = is_file(PIN_FILE) ? trim(file_get_contents(PIN_FILE)) : '';

/* ─────────────────────────────────────────────────────────
 *  HTML output
 * ───────────────────────────────────────────────────────── */
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ESP32 PhotoFrame Web – Admin</title>
<style>
  :root {
    --bg: #0f0f12;
    --surface: #1a1a22;
    --border: #2e2e3a;
    --accent: #6c63ff;
    --accent2: #ff6584;
    --text: #e0e0f0;
    --muted: #888;
    --radius: 10px;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { background: var(--bg); color: var(--text); font-family: system-ui, sans-serif; min-height: 100vh; }

  /* ── Login ────────────────────── */
  .login-wrap {
    display: flex; align-items: center; justify-content: center; min-height: 100vh;
  }
  .login-card {
    background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
    padding: 2.5rem 2rem; width: min(360px, 90vw);
  }
  .login-card h1 { font-size: 1.4rem; margin-bottom: 1.5rem; text-align: center; }
  .login-card h1 span { color: var(--accent); }

  /* ── Layout ───────────────────── */
  header {
    background: var(--surface); border-bottom: 1px solid var(--border);
    padding: .9rem 1.5rem; display: flex; align-items: center; justify-content: space-between;
  }
  header h1 { font-size: 1.1rem; }
  header h1 span { color: var(--accent); }
  .logout { color: var(--muted); text-decoration: none; font-size: .85rem; }
  .logout:hover { color: var(--accent2); }

  main { max-width: 1100px; margin: 2rem auto; padding: 0 1rem; }

  /* ── Cards ────────────────────── */
  .card {
    background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
    padding: 1.5rem; margin-bottom: 1.5rem;
  }
  .card h2 { font-size: 1rem; margin-bottom: 1rem; color: var(--accent); }

  /* ── Forms ────────────────────── */
  label { display: block; font-size: .85rem; color: var(--muted); margin-bottom: .3rem; }
  input[type=text], input[type=password], input[type=file] {
    width: 100%; background: var(--bg); border: 1px solid var(--border); border-radius: 6px;
    color: var(--text); padding: .55rem .75rem; font-size: .95rem; margin-bottom: 1rem;
  }
  input[type=file] { cursor: pointer; }
  .btn {
    display: inline-block; padding: .6rem 1.4rem; border-radius: 6px; border: none;
    cursor: pointer; font-size: .9rem; font-weight: 600; transition: opacity .15s;
  }
  .btn:hover { opacity: .85; }
  .btn-primary { background: var(--accent); color: #fff; }
  .btn-danger  { background: var(--accent2); color: #fff; padding: .35rem .8rem; font-size: .8rem; }
  .btn-pin     { background: #444; color: #fff; padding: .35rem .8rem; font-size: .8rem; }
  .btn-pin.active { background: #f5a623; color: #000; }
  .thumb.pinned { outline: 2px solid #f5a623; }

  /* ── Alerts ───────────────────── */
  .alert { border-radius: 6px; padding: .75rem 1rem; margin-bottom: 1rem; font-size: .9rem; }
  .alert-error   { background: #3a1a22; border: 1px solid var(--accent2); color: #ffb3c0; }
  .alert-success { background: #1a2e2a; border: 1px solid #3ddc97; color: #a0f0cc; }

  /* ── URL-Info-Box ─────────────── */
  .url-box {
    background: var(--bg); border: 1px solid var(--border); border-radius: 6px;
    padding: .6rem .9rem; font-family: monospace; font-size: .9rem; word-break: break-all;
    color: #a0c0ff;
  }

  /* ── Bildergalerie ────────────── */
  .gallery {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem;
  }
  .thumb {
    background: var(--bg); border: 1px solid var(--border); border-radius: var(--radius);
    overflow: hidden; position: relative;
  }
  .thumb img { display: block; width: 100%; aspect-ratio: 800/480; object-fit: cover; }
  .thumb-footer {
    padding: .5rem .6rem; display: flex; flex-direction: column; gap: .3rem;
  }
  .thumb-name { font-size: .75rem; color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .empty { color: var(--muted); font-size: .9rem; }

  /* ── Stats ────────────────────── */
  .stats { display: flex; gap: 1.5rem; flex-wrap: wrap; }
  .stat { text-align: center; }
  .stat-val { font-size: 2rem; font-weight: 700; color: var(--accent); }
  .stat-lbl { font-size: .75rem; color: var(--muted); }
</style>
</head>
<body>

<?php if (!isLoggedIn()): ?>
<!-- ═══════════════════ LOGIN ═══════════════════ -->
<div class="login-wrap">
  <div class="login-card">
    <h1>📷 ESP32 PhotoFrame <span>Web</span></h1>
    <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="action" value="login">
      <label>Username</label>
      <input type="text" name="username" autocomplete="username" required>
      <label>Password</label>
      <input type="password" name="password" autocomplete="current-password" required>
      <button class="btn btn-primary" style="width:100%">Sign in</button>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ═══════════════════ ADMIN ═══════════════════ -->
<header>
  <h1>� ESP32 PhotoFrame <span>Web</span></h1>
  <a class="logout" href="?logout">Sign out</a>
</header>

<main>

  <?php if ($error):   ?><div class="alert alert-error"  ><?= $error   ?></div><?php endif; ?>
  <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>

  <!-- ── Image URL for the frame ── -->
  <div class="card">
    <h2>Image URL for the ESP32 PhotoFrame Web</h2>
    <p style="font-size:.85rem;color:var(--muted);margin-bottom:.7rem">
      This URL returns a random image on every request.<br>
      Protected via <strong>Bearer Token</strong> – Header: <code>Authorization: Bearer &lt;token&gt;</code>
    </p>
    <?php
      $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
      $host  = $_SERVER['HTTP_HOST'];
      $base  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
      $imgUrl = $proto . '://' . $host . $base . '/image.php';
    ?>
    <div class="url-box"><?= h($imgUrl) ?></div>
    <p style="font-size:.8rem;color:var(--muted);margin-top:.5rem">
      Token (from config.php): <span style="color:#a0c0ff;font-family:monospace;word-break:break-all"><?= h(FRAME_TOKEN) ?></span>
    </p>
  </div>

  <!-- ── Stats ── -->
  <div class="card">
    <h2>Overview</h2>
    <div class="stats">
      <div class="stat">
        <div class="stat-val"><?= count($images) ?></div>
        <div class="stat-lbl">Images stored</div>
      </div>
      <div class="stat">
        <?php
          $totalBytes = array_sum(array_map('filesize', $images));
          echo '<div class="stat-val">' . round($totalBytes / 1024 / 1024, 1) . ' MB</div>';
        ?>
        <div class="stat-lbl">Disk usage</div>
      </div>
      <div class="stat">
        <div class="stat-val"><?= FRAME_WIDTH ?>×<?= FRAME_HEIGHT ?></div>
        <div class="stat-lbl">Resolution (px)</div>
      </div>
    </div>
  </div>

  <!-- ── Upload ── -->
  <div class="card">
    <h2>Upload images</h2>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="upload">
      <label>Image files (JPEG, PNG, GIF, WebP) – multiple selection supported</label>
      <input type="file" name="photos[]" accept="image/*" multiple required>
      <button class="btn btn-primary" type="submit">Upload &amp; scale</button>
    </form>
  </div>

  <!-- ── Gallery ── -->
  <div class="card">
    <h2>Uploaded images (<?= count($images) ?>)</h2>
    <?php if (empty($images)): ?>
      <p class="empty">No images uploaded yet.</p>
    <?php else: ?>
      <div class="gallery">
        <?php foreach ($images as $imgPath):
          $fname    = basename($imgPath);
          $thumb    = 'uploads/' . rawurlencode($fname);
          $isPinned = ($fname === $pinnedImg);
        ?>
        <div class="thumb<?= $isPinned ? ' pinned' : '' ?>">
          <?php if ($isPinned): ?><div style="background:#f5a623;color:#000;font-size:.7rem;font-weight:700;text-align:center;padding:.2rem">📌 NEXT IMAGE</div><?php endif; ?>
          <img src="<?= h($thumb) ?>" alt="<?= h($fname) ?>" loading="lazy">
          <div class="thumb-footer">
            <span class="thumb-name"><?= h($fname) ?></span>
            <div style="display:flex;gap:.4rem">
              <form method="post">
                <input type="hidden" name="action" value="pin">
                <input type="hidden" name="file"   value="<?= h($fname) ?>">
                <button type="submit" class="btn btn-pin<?= $isPinned ? ' active' : '' ?>"><?= $isPinned ? '📌 Unpin' : '📌 Pin' ?></button>
              </form>
              <form method="post" onsubmit="return confirm('Really delete this image?')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="file"   value="<?= h($fname) ?>">
                <button type="submit" class="btn btn-danger">🗑️ Delete</button>
              </form>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</main>
<?php endif; ?>
</body>
</html>
