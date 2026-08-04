<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require APP_DIR . '/captcha.php';

if (!captcha_has_gd()) {
    http_response_code(404);
    exit;
}

// สร้างโจทย์ใหม่ทุกครั้งที่ขอรูป (ผู้ใช้กดรีเฟรชได้)
captcha_new();
captcha_render_png((string)($_SESSION['captcha']['answer'] ?? ''));
