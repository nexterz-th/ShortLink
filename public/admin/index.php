<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

$user = admin_start('ภาพรวม', 'index');

$stats   = dashboard_stats();
$series  = clicks_series(14);
$maxDay  = max(1, max(array_column($series, 'count')));
$devices = click_breakdown('device');
$browsers = click_breakdown('browser');
$totalDev = max(1, array_sum(array_column($devices, 'c')));
$totalBr  = max(1, array_sum(array_column($browsers, 'c')));
$deviceName = ['mobile' => 'มือถือ', 'tablet' => 'แท็บเล็ต', 'desktop' => 'คอมพิวเตอร์'];

$top = db()->query("SELECT * FROM links ORDER BY clicks DESC, id DESC LIMIT 8")->fetchAll();
$recent = db()->query("SELECT * FROM links ORDER BY id DESC LIMIT 8")->fetchAll();

flash_render();
?>
<div class="stat-grid">
  <div class="stat"><div class="label">ลิงก์ทั้งหมด</div><div class="value"><?= number_format($stats['links']) ?></div><div class="delta">ใช้งานอยู่ <?= number_format($stats['active']) ?></div></div>
  <div class="stat"><div class="label">คลิกสะสม</div><div class="value"><?= number_format($stats['clicks']) ?></div><div class="delta">ตลอดอายุการใช้งาน</div></div>
  <div class="stat"><div class="label">คลิกวันนี้</div><div class="value"><?= number_format($stats['clicks_today']) ?></div><div class="delta">นับจากเที่ยงคืน</div></div>
  <div class="stat"><div class="label">ลิงก์ใหม่วันนี้</div><div class="value"><?= number_format($stats['links_today']) ?></div><div class="delta">สร้างวันนี้</div></div>
</div>

<div class="card">
  <div class="card-head">
    <h3>ยอดคลิกรายวัน (14 วันล่าสุด)</h3>
    <span class="badge badge-brand">รวม <?= number_format(array_sum(array_column($series, 'count'))) ?> คลิก</span>
  </div>
  <div class="card-pad">
    <div class="bars">
      <?php foreach ($series as $d): ?>
        <div class="bar" title="<?= e($d['date']) ?> · <?= $d['count'] ?> คลิก">
          <i style="height:<?= round($d['count'] / $maxDay * 100) ?>%"></i>
          <span><?= e($d['label']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:22px">
  <div class="card">
    <div class="card-head"><h3>อุปกรณ์</h3></div>
    <div class="card-pad stack">
      <?php if (!$devices): ?><div class="empty small">ยังไม่มีข้อมูล</div><?php endif; ?>
      <?php foreach ($devices as $d): ?>
        <div>
          <div class="row-between small"><span><?= e($deviceName[$d['label']] ?? $d['label']) ?></span><span class="muted"><?= number_format((int)$d['c']) ?></span></div>
          <div class="meter"><i style="width:<?= round($d['c'] / $totalDev * 100) ?>%"></i></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h3>เบราว์เซอร์</h3></div>
    <div class="card-pad stack">
      <?php if (!$browsers): ?><div class="empty small">ยังไม่มีข้อมูล</div><?php endif; ?>
      <?php foreach ($browsers as $b): ?>
        <div>
          <div class="row-between small"><span><?= e($b['label']) ?></span><span class="muted"><?= number_format((int)$b['c']) ?></span></div>
          <div class="meter"><i style="width:<?= round($b['c'] / $totalBr * 100) ?>%"></i></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:22px">
  <div class="card">
    <div class="card-head"><h3>ลิงก์ยอดนิยม</h3><a class="small" href="links.php?sort=clicks">ดูทั้งหมด</a></div>
    <div class="table-wrap">
      <table class="table">
        <tbody>
        <?php if (!$top): ?><tr><td class="empty">ยังไม่มีลิงก์</td></tr><?php endif; ?>
        <?php foreach ($top as $l): ?>
          <tr>
            <td>
              <a class="mono" href="link-stats.php?id=<?= (int)$l['id'] ?>">/<?= e($l['code']) ?></a>
              <div class="faint truncate" style="max-width:280px"><?= e($l['title'] !== '' ? $l['title'] : $l['target_url']) ?></div>
            </td>
            <td class="right nowrap"><b><?= number_format((int)$l['clicks']) ?></b> <span class="faint">คลิก</span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h3>ลิงก์ล่าสุด</h3><a class="small" href="links.php">ดูทั้งหมด</a></div>
    <div class="table-wrap">
      <table class="table">
        <tbody>
        <?php if (!$recent): ?><tr><td class="empty">ยังไม่มีลิงก์</td></tr><?php endif; ?>
        <?php foreach ($recent as $l): ?>
          <tr>
            <td>
              <a class="mono" href="link-stats.php?id=<?= (int)$l['id'] ?>">/<?= e($l['code']) ?></a>
              <div class="faint truncate" style="max-width:280px"><?= e($l['target_url']) ?></div>
            </td>
            <td class="right faint nowrap"><?= e(time_ago($l['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php admin_end(); ?>
