# ESP32 PhotoFrame Web

PHP-based web interface for ESP32 digital photo frames.  
Upload, manage and display images via a random-image URL on your frame.

---

## Features

- **Admin interface** – upload, preview & delete images (password protected)  
- **Random image** – `image.php` returns a different image on every request (Bearer Token auth)  
- **Auto-scaling** – every image is resized to **800 × 480 px** (cover mode, centre crop – no black bars)  
- **Pin** – mark one image to be shown on the very next request, then back to random  
- **Multi-upload** – select multiple files at once  
- **Security** – config.php never served publicly, upload directory locked via `.htaccess`

---

## Directory structure

```
picture-frame-os/
├── config.php        ← passwords & settings (NOT publicly accessible)
├── index.php         ← admin interface
├── image.php         ← image URL for the frame
├── .htaccess         ← security headers, block config.php
└── uploads/
    ├── .htaccess     ← no PHP execution in upload folder
    └── .pinned       ← stores the pinned filename (auto-managed)
```

---

## Installation

1. Copy files to a PHP-capable web server (Apache + mod_rewrite recommended)
2. Open `config.php` and **change the passwords**:
   ```php
   define('ADMIN_PASSWORD', 'yourSecurePassword');
   define('FRAME_TOKEN',    'yourBearerToken');
   ```
3. The `uploads/` directory must be **writable** by the web server:
   ```bash
   chmod 755 uploads/
   chown www-data:www-data uploads/
   ```
4. PHP extension **GD** must be enabled (`extension=gd` in php.ini)

---

## Usage

### Admin interface
`https://your-domain.com/`  
→ Log in with credentials from `config.php` → upload / pin / delete images

### Image URL for the ESP32 PhotoFrame
```
https://your-domain.com/image.php
```
Set the request header:
```
Authorization: Bearer YOUR_TOKEN
```

---

## Requirements

- PHP ≥ 7.4 with **GD** extension  
- Apache with `mod_rewrite`, `mod_headers`  
- HTTPS recommended (Let's Encrypt)
