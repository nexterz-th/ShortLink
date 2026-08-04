<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';
require APP_DIR . '/links.php';

$user = require_login();

$where = [];
$params = [];
if ($user['role'] !== 'admin') {
    $where[] = 'l.user_id = ?';
    $params[] = (int)$user['id'];
}
if ($q = trim((string)($_GET['q'] ?? ''))) {
    $where[] = '(l.code LIKE ? OR l.target_url LIKE ? OR l.title LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%");
}
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$st = db()->prepare("SELECT l.code, l.title, l.target_url, l.clicks, l.is_active, l.expires_at, l.created_at, u.username
                     FROM links l LEFT JOIN users u ON u.id = l.user_id $sqlWhere ORDER BY l.id DESC");
$st->execute($params);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="links-' . date('Y-m-d') . '.csv"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM ให้ Excel อ่านภาษาไทยได้
fputcsv($out, ['โค้ด', 'ลิงก์ย่อ', 'ชื่อ', 'ปลายทาง', 'คลิก', 'สถานะ', 'วันหมดอายุ', 'สร้างเมื่อ', 'ผู้สร้าง']);
while ($r = $st->fetch()) {
    fputcsv($out, [
        $r['code'], short_url($r['code']), $r['title'], $r['target_url'], $r['clicks'],
        $r['is_active'] ? 'ใช้งาน' : 'ปิด', $r['expires_at'] ?? '', $r['created_at'], $r['username'] ?? '',
    ]);
}
fclose($out);
