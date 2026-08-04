<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

$user = require_login();

/* ---------- การกระทำ (เปิด/ปิด/ลบ) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $scope = $user['role'] === 'admin' ? '' : ' AND user_id = ' . (int)$user['id'];
        if ($action === 'delete') {
            db()->prepare("DELETE FROM links WHERE id IN ($in)$scope")->execute($ids);
            flash(count($ids) . ' ลิงก์ถูกลบแล้ว');
        } elseif ($action === 'enable' || $action === 'disable') {
            $v = $action === 'enable' ? 1 : 0;
            db()->prepare("UPDATE links SET is_active = $v WHERE id IN ($in)$scope")->execute($ids);
            flash(($v ? 'เปิด' : 'ปิด') . 'ใช้งาน ' . count($ids) . ' ลิงก์แล้ว');
        }
    } else {
        flash('กรุณาเลือกลิงก์อย่างน้อยหนึ่งรายการ', 'warn');
    }
    header('Location: links.php?' . (string)($_SERVER['QUERY_STRING'] ?? ''));
    exit;
}

admin_start('จัดการลิงก์', 'links');

/* ---------- ตัวกรอง ---------- */
$q      = trim((string)($_GET['q'] ?? ''));
$status = (string)($_GET['status'] ?? '');
$sort   = (string)($_GET['sort'] ?? 'new');
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 20;

$where = [];
$params = [];
if ($user['role'] !== 'admin') {
    $where[] = 'l.user_id = ?';
    $params[] = (int)$user['id'];
}
if ($q !== '') {
    $where[] = '(l.code LIKE ? OR l.target_url LIKE ? OR l.title LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%");
}
if ($status === 'active') {
    $where[] = 'l.is_active = 1 AND (l.expires_at IS NULL OR l.expires_at > NOW())';
} elseif ($status === 'inactive') {
    $where[] = 'l.is_active = 0';
} elseif ($status === 'expired') {
    $where[] = 'l.expires_at IS NOT NULL AND l.expires_at <= NOW()';
}
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$order = ['new' => 'l.id DESC', 'old' => 'l.id ASC', 'clicks' => 'l.clicks DESC, l.id DESC', 'code' => 'l.code ASC'][$sort] ?? 'l.id DESC';

$stTotal = db()->prepare("SELECT COUNT(*) FROM links l $sqlWhere");
$stTotal->execute($params);
$total = (int)$stTotal->fetchColumn();
$pages = max(1, (int)ceil($total / $per));
$page  = min($page, $pages);

$st = db()->prepare("SELECT l.*, u.username FROM links l LEFT JOIN users u ON u.id = l.user_id
                     $sqlWhere ORDER BY $order LIMIT $per OFFSET " . (($page - 1) * $per));
$st->execute($params);
$rows = $st->fetchAll();

$qs = function (array $over = []) { return http_build_query(array_merge($_GET, $over)); };

flash_render();
?>
<form method="get" class="card card-pad">
  <div class="row" style="flex-wrap:wrap;gap:10px">
    <input class="input grow" name="q" value="<?= e($q) ?>" placeholder="ค้นหาโค้ด ชื่อ หรือ URL ปลายทาง" style="min-width:220px">
    <select class="select" name="status" style="width:auto">
      <option value="">ทุกสถานะ</option>
      <option value="active"   <?= $status === 'active' ? 'selected' : '' ?>>ใช้งานอยู่</option>
      <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>ปิดใช้งาน</option>
      <option value="expired"  <?= $status === 'expired' ? 'selected' : '' ?>>หมดอายุ</option>
    </select>
    <select class="select" name="sort" style="width:auto">
      <option value="new"    <?= $sort === 'new' ? 'selected' : '' ?>>ใหม่ล่าสุด</option>
      <option value="old"    <?= $sort === 'old' ? 'selected' : '' ?>>เก่าสุด</option>
      <option value="clicks" <?= $sort === 'clicks' ? 'selected' : '' ?>>คลิกมากสุด</option>
      <option value="code"   <?= $sort === 'code' ? 'selected' : '' ?>>เรียงตามโค้ด</option>
    </select>
    <button class="btn" type="submit">ค้นหา</button>
    <a class="btn btn-soft" href="link-edit.php">➕ สร้างลิงก์ใหม่</a>
    <a class="btn btn-ghost" href="export.php?<?= e($qs()) ?>">⬇️ ส่งออก CSV</a>
  </div>
</form>

<form method="post" data-confirm="ยืนยันการทำรายการกับลิงก์ที่เลือก?">
<input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
<div class="card">
  <div class="card-head">
    <div class="row">
      <h3>ลิงก์ทั้งหมด</h3>
      <span class="badge"><?= number_format($total) ?> รายการ</span>
    </div>
    <div class="row">
      <button class="btn btn-ghost btn-sm" name="action" value="enable">เปิดใช้งาน</button>
      <button class="btn btn-ghost btn-sm" name="action" value="disable">ปิดใช้งาน</button>
      <button class="btn btn-danger btn-sm" name="action" value="delete">ลบ</button>
    </div>
  </div>

  <?php if (!$rows): ?>
    <div class="empty"><div class="ico">🔗</div><p>ยังไม่มีลิงก์ที่ตรงกับเงื่อนไข</p><a class="btn btn-soft btn-sm" href="link-edit.php">สร้างลิงก์แรก</a></div>
  <?php else: ?>
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th style="width:34px"><input type="checkbox" onclick="document.querySelectorAll('input[name=\'ids[]\']').forEach(c=>c.checked=this.checked)"></th>
          <th>ลิงก์ย่อ</th>
          <th>ปลายทาง</th>
          <th class="right">คลิก</th>
          <th>สถานะ</th>
          <th>สร้างเมื่อ</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $l): $su = short_url($l['code']); $reason = link_unavailable_reason($l); ?>
        <tr>
          <td><input type="checkbox" name="ids[]" value="<?= (int)$l['id'] ?>"></td>
          <td>
            <a class="mono" href="<?= e($su) ?>" target="_blank" rel="noopener">/<?= e($l['code']) ?></a>
            <?php if ($l['title'] !== ''): ?><div class="faint truncate" style="max-width:220px"><?= e($l['title']) ?></div><?php endif; ?>
          </td>
          <td><div class="truncate" style="max-width:320px"><a href="<?= e($l['target_url']) ?>" target="_blank" rel="noopener nofollow"><?= e($l['target_url']) ?></a></div>
              <?php if ($l['username']): ?><div class="faint">โดย <?= e($l['username']) ?></div><?php endif; ?></td>
          <td class="right"><b><?= number_format((int)$l['clicks']) ?></b></td>
          <td>
            <?php if ($reason): ?>
              <span class="badge badge-danger"><?= e($reason === 'ลิงก์นี้ถูกปิดใช้งาน' ? 'ปิดใช้งาน' : ($reason === 'ลิงก์นี้หมดอายุแล้ว' ? 'หมดอายุ' : 'ครบจำนวน')) ?></span>
            <?php else: ?>
              <span class="badge badge-success">ใช้งานอยู่</span>
            <?php endif; ?>
            <?php if ($l['password_hash']): ?><span class="badge">🔒</span><?php endif; ?>
          </td>
          <td class="faint nowrap"><?= e(time_ago($l['created_at'])) ?></td>
          <td class="right nowrap">
            <button type="button" class="btn btn-ghost btn-sm" data-copy="<?= e($su) ?>">คัดลอก</button>
            <a class="btn btn-ghost btn-sm" href="link-stats.php?id=<?= (int)$l['id'] ?>">สถิติ</a>
            <a class="btn btn-ghost btn-sm" href="link-edit.php?id=<?= (int)$l['id'] ?>">แก้ไข</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
    <div class="pagination">
      <?php if ($page > 1): ?><a href="?<?= e($qs(['page' => $page - 1])) ?>">←</a><?php endif; ?>
      <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
        <?php if ($i === $page): ?><span class="current"><?= $i ?></span>
        <?php else: ?><a href="?<?= e($qs(['page' => $i])) ?>"><?= $i ?></a><?php endif; ?>
      <?php endfor; ?>
      <?php if ($page < $pages): ?><a href="?<?= e($qs(['page' => $page + 1])) ?>">→</a><?php endif; ?>
    </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
</form>
<?php admin_end(); ?>
