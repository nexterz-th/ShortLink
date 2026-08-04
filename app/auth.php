<?php
declare(strict_types=1);

function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_name('linksid');
    session_start();
}

function auth_user(): ?array
{
    static $user = null;
    if ($user !== null) {
        return $user;
    }
    if (empty($_SESSION['uid'])) {
        return null;
    }
    $st = db()->prepare("SELECT id, username, name, role, is_active FROM users WHERE id = ? LIMIT 1");
    $st->execute([$_SESSION['uid']]);
    $row = $st->fetch();
    if (!$row || !$row['is_active']) {
        unset($_SESSION['uid']);
        return null;
    }
    return $user = $row;
}

function require_login(): array
{
    $user = auth_user();
    if (!$user) {
        header('Location: login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? 'index.php'));
        exit;
    }
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        exit('ต้องเป็นผู้ดูแลระบบเท่านั้น');
    }
    return $user;
}

const LOGIN_MAX_FAILS   = 8;    // จำนวนครั้งที่ยอมให้ผิดต่อ IP
const LOGIN_WINDOW_MIN  = 15;   // ภายในกี่นาที

/** ถูกล็อกไม่ให้ลองเข้าสู่ระบบชั่วคราวหรือไม่ (นับจากฐานข้อมูล ล้างคุกกี้แล้วเลี่ยงไม่ได้) */
function login_locked(): bool
{
    // รองรับระบบที่ติดตั้งไว้ก่อนหน้าและยังไม่มีตารางนี้
    db()->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(45) NOT NULL,
        username VARCHAR(64) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip_time (ip, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $st = db()->prepare("SELECT COUNT(*) FROM login_attempts
                         WHERE ip = ? AND created_at > (NOW() - INTERVAL ? MINUTE)");
    $st->bindValue(1, client_ip());
    $st->bindValue(2, LOGIN_WINDOW_MIN, PDO::PARAM_INT);
    $st->execute();
    return (int)$st->fetchColumn() >= LOGIN_MAX_FAILS;
}

function login_record_fail(string $username): void
{
    db()->prepare("INSERT INTO login_attempts (ip, username) VALUES (?, ?)")
        ->execute([client_ip(), mb_substr($username, 0, 64)]);
    // เก็บกวาดข้อมูลเก่าเป็นครั้งคราว
    if (random_int(1, 20) === 1) {
        db()->exec("DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 1 DAY)");
    }
}

function login_clear_fails(): void
{
    db()->prepare("DELETE FROM login_attempts WHERE ip = ?")->execute([client_ip()]);
}

function auth_attempt(string $username, string $password): bool
{
    $st = db()->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    $st->execute([$username]);
    $row = $st->fetch();
    if (!$row || !$row['is_active'] || !password_verify($password, $row['password_hash'])) {
        // เทียบ hash หลอกเมื่อไม่พบผู้ใช้ เพื่อให้เวลาตอบสนองใกล้เคียงกัน (กันการเดาว่ามีชื่อผู้ใช้นี้จริง)
        if (!$row) {
            password_verify($password, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30M1MlGPL.2K');
        }
        return false;
    }
    session_regenerate_id(true);
    login_clear_fails();
    $_SESSION['uid'] = (int)$row['id'];
    db()->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$row['id']]);
    return true;
}

function auth_logout(): void
{
    $_SESSION = [];
    session_destroy();
}
