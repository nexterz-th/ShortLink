<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';
require APP_DIR . '/links.php';
require APP_DIR . '/layout.php';

/** เปิดหน้าในหลังบ้าน (ตรวจสิทธิ์ + วาดโครงหน้า) */
function admin_start(string $title, string $active): array
{
    $user = require_login();
    $site = (string)setting('site_name', 'Link.');

    $menu = [
        'index'    => ['ภาพรวม', '📊', 'index.php'],
        'links'    => ['จัดการลิงก์', '🔗', 'links.php'],
        'new'      => ['สร้างลิงก์ใหม่', '➕', 'link-edit.php'],
        'clicks'   => ['ประวัติการคลิก', '🕘', 'clicks.php'],
    ];
    $adminMenu = [
        'users'    => ['ผู้ใช้งาน', '👤', 'users.php'],
        'settings' => ['ตั้งค่าระบบ', '⚙️', 'settings.php'],
    ];

    page_head($title);
    ?>
    <div class="admin">
      <aside class="sidebar">
        <a class="brand" href="/"><span class="dot">🔗</span><?= e($site) ?></a>
        <?php foreach ($menu as $key => [$label, $icon, $href]): ?>
          <a class="nav-link <?= $active === $key ? 'active' : '' ?>" href="<?= $href ?>"><span><?= $icon ?></span><?= e($label) ?></a>
        <?php endforeach; ?>
        <?php if ($user['role'] === 'admin'): ?>
          <div class="nav-sep">ผู้ดูแลระบบ</div>
          <?php foreach ($adminMenu as $key => [$label, $icon, $href]): ?>
            <a class="nav-link <?= $active === $key ? 'active' : '' ?>" href="<?= $href ?>"><span><?= $icon ?></span><?= e($label) ?></a>
          <?php endforeach; ?>
        <?php endif; ?>
        <div style="margin-top:auto">
          <a class="nav-link" href="/" target="_blank"><span>🌐</span>เปิดหน้าเว็บ</a>
          <a class="nav-link" href="logout.php"><span>🚪</span>ออกจากระบบ</a>
          <div class="sidebar-credit">Made with <span class="heart">❤️</span> by NEXTERZ</div>
        </div>
      </aside>

      <div class="admin-main">
        <header class="admin-head">
          <div class="row">
            <button class="btn btn-ghost btn-icon menu-btn" type="button">☰</button>
            <h2 style="font-size:1.1rem;margin:0"><?= e($title) ?></h2>
          </div>
          <div class="row">
            <?php theme_button(); ?>
            <div class="row">
              <div class="avatar"><?= e(mb_substr($user['name'] !== '' ? $user['name'] : $user['username'], 0, 1)) ?></div>
              <div class="small nowrap"><?= e($user['name'] !== '' ? $user['name'] : $user['username']) ?></div>
            </div>
          </div>
        </header>
        <div class="admin-body">
    <?php
    return $user;
}

function admin_end(): void
{
    ?>
        </div>
      </div>
    </div>
    <?php
    page_foot();
}

/** ข้อความแจ้งผลข้ามหน้า */
function flash(?string $msg = null, string $type = 'success'): ?array
{
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function flash_render(): void
{
    if ($f = flash()) {
        $cls = $f['type'] === 'error' ? 'alert-error' : ($f['type'] === 'warn' ? 'alert-warn' : 'alert-success');
        echo '<div class="alert ' . $cls . '">' . e($f['msg']) . '</div>';
    }
}
