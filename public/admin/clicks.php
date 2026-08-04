<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

$user = require_login();
$page = max(1, (int)($_GET['page'] ?? 1));
$per  = 50;

$where = [];
$params = [];
if ($user['role'] !== 'admin') {
    $where[] = 'l.user_id = ?';
    $params[] = (int)$user['id'];
}
if ($code = trim((string)($_GET['code'] ?? ''))) {
    $where[] = 'l.code = ?';
    $params[] = $code;
}
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stTotal = db()->prepare("SELECT COUNT(*) FROM clicks c JOIN links l ON l.id = c.link_id $sqlWhere");
$stTotal->execute($params);
$total = (int)$stTotal->fetchColumn();
$pages = max(1, (int)ceil($total / $per));
$page  = min($page, $pages);

$st = db()->prepare("SELECT c.*, l.code FROM clicks c JOIN links l ON l.id = c.link_id
                     $sqlWhere ORDER BY c.id DESC LIMIT $per OFFSET " . (($page - 1) * $per));
$st->execute($params);
$rows = $st->fetchAll();

$deviceName = ['mobile' => 'มือถือ', 'tablet' => 'แท็บเล็ต', 'desktop' => 'คอมพิวเตอร์'];

admin_start('ประวัติการคลิก', 'clicks');
flash_render();
?>
<form method="get" class="card card-pad row">
  <input class="input grow" name="code" value="<?= e((string)($_GET['code'] ?? '')) ?>" placeholder="กรองตามโค้ดลิงก์ เช่น promo2026">
  <button class="btn" type="submit">กรอง</button>
  <?php if (!empty($_GET['code'])): ?><a class="btn btn-ghost" href="clicks.php">ล้าง</a><?php endif; ?>
</form>

<div class="card">
  <div class="card-head"><h3>ประวัติการคลิก</h3><span class="badge"><?= number_format($total) ?> รายการ</span></div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>เวลา</th><th>ลิงก์</th><th>อุปกรณ์</th><th>เบราว์เซอร์</th><th>ระบบ</th><th>แหล่งที่มา</th><th>IP</th></tr></thead>
      <tbody>
        <?php if (!$rows): ?><tr><td colspan="7" class="empty">ยังไม่มีข้อมูลการคลิก</td></tr><?php endif; ?>
        <?php foreach ($rows as $c): ?>
          <tr>
            <td class="nowrap"><?= e(date('d/m/Y H:i:s', strtotime($c['created_at']))) ?></td>
            <td class="mono"><a href="clicks.php?code=<?= e($c['code']) ?>">/<?= e($c['code']) ?></a></td>
            <td><?= e($deviceName[$c['device']] ?? $c['device']) ?></td>
            <td><?= e($c['browser']) ?></td>
            <td><?= e($c['os']) ?></td>
            <td><div class="truncate" style="max-width:220px"><?= e($c['referer'] !== '' ? $c['referer'] : '—') ?></div></td>
            <td class="mono faint"><?= e($c['ip']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
    <div class="pagination">
      <?php for ($i = max(1, $page - 3); $i <= min($pages, $page + 3); $i++): ?>
        <?php if ($i === $page): ?><span class="current"><?= $i ?></span>
        <?php else: ?><a href="?<?= e(http_build_query(array_merge($_GET, ['page' => $i]))) ?>"><?= $i ?></a><?php endif; ?>
      <?php endfor; ?>
    </div>
  <?php endif; ?>
</div>
<?php admin_end(); ?>
