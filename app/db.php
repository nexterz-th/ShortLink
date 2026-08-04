<?php
declare(strict_types=1);

/** ค่าออฟเซ็ตเขตเวลาปัจจุบันในรูปแบบที่ MySQL เข้าใจ เช่น +07:00 */
function db_tz_offset(): string
{
    $offset = (new DateTimeZone(date_default_timezone_get()))->getOffset(new DateTime('now'));
    return sprintf('%s%02d:%02d', $offset < 0 ? '-' : '+', intdiv(abs($offset), 3600), intdiv(abs($offset) % 3600, 60));
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $c = CFG;
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['db_host'], (int)$c['db_port'], $c['db_name']);
    try {
        $pdo = new PDO($dsn, $c['db_user'], $c['db_pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        // ตั้งเขตเวลาของ session ให้ตรงกับ PHP มิฉะนั้น NOW()/CURDATE() จะเป็น UTC
        $pdo->prepare("SET time_zone = ?")->execute([db_tz_offset()]);
    } catch (PDOException $e) {
        // ไม่แสดงรายละเอียดการเชื่อมต่อบนหน้าเว็บ ให้ดูใน error log ของโฮสต์แทน
        error_log('[link] db connect failed: ' . $e->getMessage());
        http_response_code(500);
        exit('ขออภัย ระบบไม่สามารถเชื่อมต่อฐานข้อมูลได้ในขณะนี้');
    }
    return $pdo;
}

/** สร้างตารางทั้งหมด (ใช้ตอนติดตั้ง) */
function db_migrate(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(64) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        name VARCHAR(120) NOT NULL DEFAULT '',
        role ENUM('admin','editor') NOT NULL DEFAULT 'editor',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        last_login DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS links (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(64) NOT NULL UNIQUE,
        target_url TEXT NOT NULL,
        title VARCHAR(255) NOT NULL DEFAULT '',
        note VARCHAR(255) NOT NULL DEFAULT '',
        user_id INT UNSIGNED NULL,
        clicks INT UNSIGNED NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        password_hash VARCHAR(255) NULL,
        expires_at DATETIME NULL,
        max_clicks INT UNSIGNED NULL,
        created_ip VARCHAR(45) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS clicks (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        link_id INT UNSIGNED NOT NULL,
        ip VARCHAR(45) NOT NULL DEFAULT '',
        user_agent VARCHAR(255) NOT NULL DEFAULT '',
        referer VARCHAR(255) NOT NULL DEFAULT '',
        device VARCHAR(20) NOT NULL DEFAULT '',
        browser VARCHAR(40) NOT NULL DEFAULT '',
        os VARCHAR(40) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_link (link_id),
        INDEX idx_time (created_at),
        CONSTRAINT fk_click_link FOREIGN KEY (link_id) REFERENCES links(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(45) NOT NULL,
        username VARCHAR(64) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip_time (ip, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        k VARCHAR(64) NOT NULL PRIMARY KEY,
        v TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $defaults = [
        'site_name'       => 'Link.',
        'public_shorten'  => '1',   // ให้คนทั่วไปย่อลิงก์ได้หรือไม่
        'code_length'     => '6',
        'rate_limit'      => '20',  // ครั้ง/ชั่วโมง/IP สำหรับผู้ใช้ทั่วไป
        'captcha_mode'    => 'guest', // off | guest | all
        'blocked_domains' => '',
    ];
    $st = $pdo->prepare("INSERT IGNORE INTO settings (k, v) VALUES (?, ?)");
    foreach ($defaults as $k => $v) {
        $st->execute([$k, $v]);
    }
}
