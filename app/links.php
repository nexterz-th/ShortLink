<?php
declare(strict_types=1);

/**
 * สร้างลิงก์ย่อ
 * @return array{ok:bool, error?:string, link?:array}
 */
function create_link(array $input, ?int $userId = null): array
{
    $error = null;
    $url = normalize_url((string)($input['url'] ?? ''), $error);
    if ($url === null) {
        return ['ok' => false, 'error' => $error];
    }

    $code = trim((string)($input['code'] ?? ''));
    if ($code !== '') {
        if ($msg = validate_code($code)) {
            return ['ok' => false, 'error' => $msg];
        }
    } else {
        $code = generate_code(max(4, (int)setting('code_length', '6')));
    }

    $expires = trim((string)($input['expires_at'] ?? ''));
    if ($expires !== '') {
        $ts = strtotime($expires);
        if ($ts === false) {
            return ['ok' => false, 'error' => 'รูปแบบวันหมดอายุไม่ถูกต้อง'];
        }
        $expires = date('Y-m-d H:i:s', $ts);
    } else {
        $expires = null;
    }

    $password = (string)($input['password'] ?? '');
    $maxClicks = (int)($input['max_clicks'] ?? 0);

    $st = db()->prepare("INSERT INTO links (code, target_url, title, note, user_id, password_hash, expires_at, max_clicks, is_active, created_ip)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $st->execute([
        $code,
        $url,
        mb_substr(trim((string)($input['title'] ?? '')), 0, 255),
        mb_substr(trim((string)($input['note'] ?? '')), 0, 255),
        $userId,
        $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null,
        $expires,
        $maxClicks > 0 ? $maxClicks : null,
        isset($input['is_active']) ? (int)(bool)$input['is_active'] : 1,
        client_ip(),
    ]);

    return ['ok' => true, 'link' => find_link_by_id((int)db()->lastInsertId())];
}

function find_link_by_id(int $id): ?array
{
    $st = db()->prepare("SELECT * FROM links WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function find_link_by_code(string $code): ?array
{
    $st = db()->prepare("SELECT * FROM links WHERE code = ? LIMIT 1");
    $st->execute([$code]);
    return $st->fetch() ?: null;
}

/** เหตุผลที่ลิงก์ใช้งานไม่ได้ (null = ใช้ได้) */
function link_unavailable_reason(array $link): ?string
{
    if (!$link['is_active']) {
        return 'ลิงก์นี้ถูกปิดใช้งาน';
    }
    if ($link['expires_at'] !== null && strtotime($link['expires_at']) < time()) {
        return 'ลิงก์นี้หมดอายุแล้ว';
    }
    if ($link['max_clicks'] !== null && (int)$link['clicks'] >= (int)$link['max_clicks']) {
        return 'ลิงก์นี้ถูกใช้งานครบจำนวนที่กำหนดแล้ว';
    }
    return null;
}

/** บันทึกสถิติการคลิก */
function record_click(array $link): void
{
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $meta = parse_ua($ua);
    db()->prepare("INSERT INTO clicks (link_id, ip, user_agent, referer, device, browser, os)
                   VALUES (?, ?, ?, ?, ?, ?, ?)")
        ->execute([
            $link['id'],
            client_ip(),
            mb_substr($ua, 0, 255),
            mb_substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 255),
            $meta['device'], $meta['browser'], $meta['os'],
        ]);
    db()->prepare("UPDATE links SET clicks = clicks + 1 WHERE id = ?")->execute([$link['id']]);
}

/** สถิติรวมของระบบ */
function dashboard_stats(): array
{
    $pdo = db();
    return [
        'links'        => (int)$pdo->query("SELECT COUNT(*) FROM links")->fetchColumn(),
        'active'       => (int)$pdo->query("SELECT COUNT(*) FROM links WHERE is_active = 1")->fetchColumn(),
        'clicks'       => (int)$pdo->query("SELECT COALESCE(SUM(clicks),0) FROM links")->fetchColumn(),
        'clicks_today' => (int)$pdo->query("SELECT COUNT(*) FROM clicks WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
        'links_today'  => (int)$pdo->query("SELECT COUNT(*) FROM links WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
    ];
}

/** ยอดคลิกรายวันย้อนหลัง N วัน (เติมวันที่ไม่มีข้อมูลด้วย 0) */
function clicks_series(int $days = 14, ?int $linkId = null): array
{
    $sql = "SELECT DATE(created_at) d, COUNT(*) c FROM clicks
            WHERE created_at >= (CURDATE() - INTERVAL :days DAY)";
    $params = [':days' => $days - 1];
    if ($linkId !== null) {
        $sql .= " AND link_id = :lid";
        $params[':lid'] = $linkId;
    }
    $sql .= " GROUP BY d";
    $st = db()->prepare($sql);
    foreach ($params as $k => $v) {
        $st->bindValue($k, $v, PDO::PARAM_INT);
    }
    $st->execute();
    $map = [];
    foreach ($st->fetchAll() as $row) {
        $map[$row['d']] = (int)$row['c'];
    }
    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i day"));
        $out[] = ['date' => $d, 'label' => date('j/n', strtotime($d)), 'count' => $map[$d] ?? 0];
    }
    return $out;
}

/** สรุปตามคอลัมน์ เช่น device / browser / os */
function click_breakdown(string $column, ?int $linkId = null, int $limit = 6): array
{
    if (!in_array($column, ['device', 'browser', 'os', 'referer'], true)) {
        return [];
    }
    $sql = "SELECT COALESCE(NULLIF($column,''),'ไม่ระบุ') AS label, COUNT(*) AS c FROM clicks";
    $params = [];
    if ($linkId !== null) {
        $sql .= " WHERE link_id = ?";
        $params[] = $linkId;
    }
    $sql .= " GROUP BY label ORDER BY c DESC LIMIT " . (int)$limit;
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}
