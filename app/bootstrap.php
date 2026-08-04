<?php
declare(strict_types=1);

// ไม่แสดงข้อผิดพลาดบนหน้าเว็บ (กันข้อมูลเซิร์ฟเวอร์รั่ว) แต่ยังบันทึกลง log ของโฮสต์
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/config.php';
require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/auth.php';

if (!APP_INSTALLED && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') {
    header('Location: /install.php');
    exit;
}

session_boot();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
