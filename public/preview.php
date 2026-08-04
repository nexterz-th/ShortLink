<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require APP_DIR . '/links.php';
require APP_DIR . '/layout.php';

$code = (string)($_GET['code'] ?? '');
$link = $code !== '' ? find_link_by_code($code) : null;

if (!$link) {
    http_response_code(404);
    page_head('ไม่พบลิงก์');
    echo '<div class="auth-wrap"><div class="card card-pad auth-card center"><div style="font-size:2.6rem">🔍</div><h2>ไม่พบลิงก์นี้</h2><a class="btn btn-soft btn-block" href="/">กลับหน้าแรก</a></div></div>';
    page_foot();
    exit;
}

$series  = clicks_series(14, (int)$link['id']);
$maxDay  = max(1, max(array_column($series, 'count')));
$devices = click_breakdown('device', (int)$link['id']);
$totalDev = max(1, array_sum(array_column($devices, 'c')));
$su = short_url($link['code']);
$deviceName = ['mobile' => 'มือถือ', 'tablet' => 'แท็บเล็ต', 'desktop' => 'คอมพิวเตอร์'];

page_head('สถิติลิงก์ ' . $link['code']);
?>
<header class="topbar">
  <div class="container inner">
    <a class="brand" href="/"><span class="dot">🔗</span><?= e((string)setting('site_name', 'Link.')) ?></a>
    <?php theme_button(); ?>
  </div>
</header>

<main class="container container-sm" style="padding-top:34px;padding-bottom:60px">
  <div class="stack">
    <div class="card card-pad">
      <div class="faint">ลิงก์ย่อ</div>
      <div class="row-between">
        <a class="link" href="<?= e($su) ?>" style="font-size:1.3rem;font-weight:600"><?= e($su) ?></a>
        <button class="btn btn-sm" data-copy="<?= e($su) ?>">คัดลอก</button>
      </div>
      <?php if ($link['title'] !== ''): ?><div style="margin-top:6px"><?= e($link['title']) ?></div><?php endif; ?>
      <?php if ($link['password_hash']): ?>
        <?php /* ลิงก์ที่ล็อกด้วยรหัสผ่าน ต้องไม่เปิดเผยปลายทางในหน้าสาธารณะ */ ?>
        <div class="faint" style="margin-top:4px">→ ปลายทางถูกซ่อนไว้ ต้องใส่รหัสผ่านก่อนเข้าใช้งาน</div>
      <?php else: ?>
        <div class="faint truncate" style="margin-top:4px">→ <?= e($link['target_url']) ?></div>
      <?php endif; ?>
      <div class="row" style="margin-top:12px">
        <?php if (link_unavailable_reason($link)): ?>
          <span class="badge badge-danger"><?= e(link_unavailable_reason($link)) ?></span>
        <?php else: ?>
          <span class="badge badge-success">ใช้งานได้</span>
        <?php endif; ?>
        <?php if ($link['password_hash']): ?><span class="badge">🔒 มีรหัสผ่าน</span><?php endif; ?>
        <span class="badge">สร้างเมื่อ <?= e(time_ago($link['created_at'])) ?></span>
      </div>
    </div>

    <div class="stat-grid">
      <div class="stat"><div class="label">คลิกทั้งหมด</div><div class="value"><?= number_format((int)$link['clicks']) ?></div></div>
      <div class="stat"><div class="label">คลิก 14 วันล่าสุด</div><div class="value"><?= number_format(array_sum(array_column($series, 'count'))) ?></div></div>
      <div class="stat"><div class="label">คลิกวันนี้</div><div class="value"><?= number_format(end($series)['count']) ?></div></div>
    </div>

    <div class="card">
      <div class="card-head"><h3>ยอดคลิกรายวัน (14 วัน)</h3></div>
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

    <div class="card">
      <div class="card-head"><h3>อุปกรณ์ที่ใช้เปิด</h3></div>
      <div class="card-pad stack">
        <?php if (!$devices): ?>
          <div class="empty">ยังไม่มีข้อมูลการคลิก</div>
        <?php else: foreach ($devices as $d): ?>
          <div>
            <div class="row-between small"><span><?= e($deviceName[$d['label']] ?? $d['label']) ?></span><span class="muted"><?= number_format((int)$d['c']) ?></span></div>
            <div class="meter"><i style="width:<?= round($d['c'] / $totalDev * 100) ?>%"></i></div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  </div>
</main>
<?php site_footer(); ?>
<?php page_foot(); ?>
