<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

$user = require_login();
$id = (int)($_GET['id'] ?? 0);
$link = find_link_by_id($id);

if (!$link) {
    http_response_code(404);
    exit('ไม่พบลิงก์');
}
if ($user['role'] !== 'admin' && (int)$link['user_id'] !== (int)$user['id']) {
    http_response_code(403);
    exit('คุณไม่มีสิทธิ์ดูลิงก์นี้');
}

$days   = max(7, min(90, (int)($_GET['days'] ?? 14)));
$series = clicks_series($days, $id);
$maxDay = max(1, max(array_column($series, 'count')));
$su     = short_url($link['code']);

$sections = [
    'device'  => 'อุปกรณ์',
    'browser' => 'เบราว์เซอร์',
    'os'      => 'ระบบปฏิบัติการ',
    'referer' => 'แหล่งที่มา',
];
$deviceName = ['mobile' => 'มือถือ', 'tablet' => 'แท็บเล็ต', 'desktop' => 'คอมพิวเตอร์'];

$stRecent = db()->prepare("SELECT * FROM clicks WHERE link_id = ? ORDER BY id DESC LIMIT 30");
$stRecent->execute([$id]);
$recent = $stRecent->fetchAll();

admin_start('สถิติ /' . $link['code'], 'links');
flash_render();
?>
<div class="card card-pad">
  <div class="row-between">
    <div class="grow" style="min-width:0">
      <div class="row">
        <a class="mono" style="font-size:1.25rem;font-weight:600" href="<?= e($su) ?>" target="_blank" rel="noopener"><?= e($su) ?></a>
        <?php if (link_unavailable_reason($link)): ?>
          <span class="badge badge-danger"><?= e(link_unavailable_reason($link)) ?></span>
        <?php else: ?><span class="badge badge-success">ใช้งานอยู่</span><?php endif; ?>
      </div>
      <?php if ($link['title'] !== ''): ?><div><?= e($link['title']) ?></div><?php endif; ?>
      <div class="faint truncate">→ <?= e($link['target_url']) ?></div>
    </div>
    <div class="row">
      <button class="btn btn-ghost btn-sm" data-copy="<?= e($su) ?>">คัดลอก</button>
      <a class="btn btn-soft btn-sm" href="link-edit.php?id=<?= $id ?>">แก้ไข</a>
    </div>
  </div>
</div>

<div class="stat-grid">
  <div class="stat"><div class="label">คลิกทั้งหมด</div><div class="value"><?= number_format((int)$link['clicks']) ?></div></div>
  <div class="stat"><div class="label">คลิกใน <?= $days ?> วัน</div><div class="value"><?= number_format(array_sum(array_column($series, 'count'))) ?></div></div>
  <div class="stat"><div class="label">คลิกวันนี้</div><div class="value"><?= number_format(end($series)['count']) ?></div></div>
  <div class="stat"><div class="label">เฉลี่ยต่อวัน</div><div class="value"><?= number_format(array_sum(array_column($series, 'count')) / $days, 1) ?></div></div>
</div>

<div class="card">
  <div class="card-head">
    <h3>ยอดคลิกรายวัน</h3>
    <div class="row">
      <?php foreach ([7, 14, 30, 90] as $d): ?>
        <a class="badge <?= $days === $d ? 'badge-brand' : '' ?>" href="?id=<?= $id ?>&days=<?= $d ?>"><?= $d ?> วัน</a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card-pad">
    <div class="bars">
      <?php foreach ($series as $i => $d): ?>
        <div class="bar" title="<?= e($d['date']) ?> · <?= $d['count'] ?> คลิก">
          <i style="height:<?= round($d['count'] / $maxDay * 100) ?>%"></i>
          <?php if ($days <= 30 || $i % 5 === 0): ?><span><?= e($d['label']) ?></span><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:22px">
  <?php foreach ($sections as $col => $label):
      $rows = click_breakdown($col, $id, 8);
      $sum  = max(1, array_sum(array_column($rows, 'c'))); ?>
    <div class="card">
      <div class="card-head"><h3><?= e($label) ?></h3></div>
      <div class="card-pad stack">
        <?php if (!$rows): ?><div class="empty small">ยังไม่มีข้อมูล</div><?php endif; ?>
        <?php foreach ($rows as $r):
            $name = $col === 'device' ? ($deviceName[$r['label']] ?? $r['label']) : $r['label'];
            if ($col === 'referer' && $name !== 'ไม่ระบุ') {
                $name = (string)(parse_url($name, PHP_URL_HOST) ?: $name);
            } ?>
          <div>
            <div class="row-between small"><span class="truncate"><?= e($name) ?></span><span class="muted nowrap"><?= number_format((int)$r['c']) ?></span></div>
            <div class="meter"><i style="width:<?= round($r['c'] / $sum * 100) ?>%"></i></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="card">
  <div class="card-head"><h3>การคลิกล่าสุด</h3><span class="badge">30 รายการล่าสุด</span></div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>เวลา</th><th>อุปกรณ์</th><th>เบราว์เซอร์</th><th>ระบบ</th><th>แหล่งที่มา</th><th>IP</th></tr></thead>
      <tbody>
        <?php if (!$recent): ?><tr><td colspan="6" class="empty">ยังไม่มีการคลิก</td></tr><?php endif; ?>
        <?php foreach ($recent as $c): ?>
          <tr>
            <td class="nowrap"><?= e(date('d/m/Y H:i', strtotime($c['created_at']))) ?></td>
            <td><?= e($deviceName[$c['device']] ?? $c['device']) ?></td>
            <td><?= e($c['browser']) ?></td>
            <td><?= e($c['os']) ?></td>
            <td><div class="truncate" style="max-width:200px"><?= e($c['referer'] !== '' ? $c['referer'] : '—') ?></div></td>
            <td class="mono faint"><?= e($c['ip']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php admin_end(); ?>
