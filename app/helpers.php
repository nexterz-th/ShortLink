<?php
declare(strict_types=1);

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function setting(string $key, ?string $default = null): ?string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query("SELECT k, v FROM settings") as $row) {
            $cache[$row['k']] = $row['v'];
        }
    }
    return $cache[$key] ?? $default;
}

function setting_set(string $key, string $value): void
{
    db()->prepare("INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)")
        ->execute([$key, $value]);
}

function base_url(): string
{
    $cfg = trim((string)(CFG['base_url'] ?? ''));
    if ($cfg !== '') {
        return rtrim($cfg, '/');
    }
    $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https://' : 'http://') . $host;
}

function short_url(string $code): string
{
    return base_url() . '/' . $code;
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/** สุ่มโค้ดที่ไม่ซ้ำ */
function generate_code(int $len = 6): string
{
    $alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $max = strlen($alphabet) - 1;
    for ($try = 0; $try < 12; $try++) {
        $code = '';
        for ($i = 0; $i < $len; $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }
        if (!code_exists($code)) {
            return $code;
        }
        if ($try === 5) {
            $len++;
        }
    }
    return bin2hex(random_bytes(6));
}

function code_exists(string $code): bool
{
    $st = db()->prepare("SELECT 1 FROM links WHERE code = ? LIMIT 1");
    $st->execute([$code]);
    return (bool)$st->fetchColumn();
}

/** คำที่ห้ามใช้เป็นโค้ด เพราะชนกับไฟล์/โฟลเดอร์จริง */
function reserved_codes(): array
{
    return ['admin', 'api', 'assets', 'install', 'index', 'go', 'unlock', 'p', 'preview',
            'captcha', 'login', 'logout', 'export', 'favicon.ico', 'robots.txt'];
}

function validate_code(string $code): ?string
{
    if (!preg_match('/^[A-Za-z0-9_-]{3,64}$/', $code)) {
        return 'โค้ดต้องเป็น a-z A-Z 0-9 _ - ความยาว 3–64 ตัวอักษร';
    }
    if (in_array(strtolower($code), reserved_codes(), true)) {
        return 'โค้ดนี้เป็นคำสงวนของระบบ';
    }
    if (code_exists($code)) {
        return 'โค้ดนี้ถูกใช้ไปแล้ว';
    }
    return null;
}

/** ตรวจ URL ปลายทาง คืนค่า URL ที่ปรับแล้ว หรือ null ถ้าไม่ผ่าน */
function normalize_url(string $url, ?string &$error = null): ?string
{
    $url = trim($url);
    if ($url === '') {
        $error = 'กรุณากรอกลิงก์ปลายทาง';
        return null;
    }
    if (!preg_match('~^[a-zA-Z][a-zA-Z0-9+.-]*://~', $url)) {
        $url = 'https://' . $url;
    }
    $parts = parse_url($url);
    if (!$parts || empty($parts['host']) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
        $error = 'ลิงก์ไม่ถูกต้อง (รองรับเฉพาะ http และ https)';
        return null;
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $error = 'รูปแบบลิงก์ไม่ถูกต้อง';
        return null;
    }
    $host = strtolower($parts['host']);
    // กันการวนซ้ำมาที่ตัวเอง
    $self = strtolower((string)parse_url(base_url(), PHP_URL_HOST));
    if ($host === $self) {
        $error = 'ไม่สามารถย่อลิงก์ของเว็บนี้เองได้';
        return null;
    }
    foreach (array_filter(array_map('trim', explode("\n", (string)setting('blocked_domains', '')))) as $blocked) {
        $blocked = strtolower(ltrim($blocked, '.'));
        if ($blocked !== '' && ($host === $blocked || str_ends_with($host, '.' . $blocked))) {
            $error = 'โดเมนนี้ถูกระงับการใช้งาน';
            return null;
        }
    }
    return $url;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $token)) {
        http_response_code(419);
        exit('เซสชันหมดอายุ กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง');
    }
}

function json_out($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** แยกข้อมูล user agent อย่างง่าย */
function parse_ua(string $ua): array
{
    $ua_l = strtolower($ua);
    $device = preg_match('/mobile|iphone|android.*mobile|windows phone/', $ua_l) ? 'mobile'
        : (preg_match('/ipad|tablet|android/', $ua_l) ? 'tablet' : 'desktop');

    $browser = 'อื่น ๆ';
    foreach ([
        'Edge' => 'edg/', 'Opera' => 'opr/|opera', 'Samsung' => 'samsungbrowser',
        'Chrome' => 'chrome|crios', 'Firefox' => 'firefox|fxios', 'Safari' => 'safari',
        'Bot' => 'bot|crawler|spider|curl|wget',
    ] as $name => $pattern) {
        if (preg_match('~' . $pattern . '~', $ua_l)) { $browser = $name; break; }
    }

    $os = 'อื่น ๆ';
    foreach ([
        'Windows' => 'windows', 'Android' => 'android', 'iOS' => 'iphone|ipad|ipod',
        'macOS' => 'mac os x|macintosh', 'Linux' => 'linux',
    ] as $name => $pattern) {
        if (preg_match('~' . $pattern . '~', $ua_l)) { $os = $name; break; }
    }

    return ['device' => $device, 'browser' => $browser, 'os' => $os];
}

/** จำกัดจำนวนครั้งการสร้างลิงก์ต่อ IP ต่อชั่วโมง */
function rate_limited(): bool
{
    $limit = (int)setting('rate_limit', '20');
    if ($limit <= 0) {
        return false;
    }
    $st = db()->prepare("SELECT COUNT(*) FROM links WHERE created_ip = ? AND user_id IS NULL AND created_at > (NOW() - INTERVAL 1 HOUR)");
    $st->execute([client_ip()]);
    return (int)$st->fetchColumn() >= $limit;
}

function time_ago(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'เมื่อสักครู่';
    if ($diff < 3600)   return floor($diff / 60) . ' นาทีที่แล้ว';
    if ($diff < 86400)  return floor($diff / 3600) . ' ชั่วโมงที่แล้ว';
    if ($diff < 2592000) return floor($diff / 86400) . ' วันที่แล้ว';
    return date('d/m/Y', strtotime($datetime));
}
