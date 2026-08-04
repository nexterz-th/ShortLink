<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require APP_DIR . '/links.php';
require APP_DIR . '/layout.php';

$code = (string)($_GET['code'] ?? '');
$link = $code !== '' ? find_link_by_code($code) : null;

/** แสดงหน้าแจ้งข้อผิดพลาดแบบเต็มจอ */
function show_notice(string $emoji, string $title, string $message, int $status = 404): void
{
    http_response_code($status);
    page_head($title);
    ?>
    <div class="auth-wrap">
      <div class="card card-pad auth-card center">
        <div style="font-size:2.6rem"><?= $emoji ?></div>
        <h2><?= e($title) ?></h2>
        <p class="muted"><?= e($message) ?></p>
        <a class="btn btn-soft btn-block" href="/">กลับหน้าแรก</a>
      </div>
    </div>
    <?php
    page_foot();
    exit;
}

if (!$link) {
    show_notice('🔍', 'ไม่พบลิงก์นี้', 'ลิงก์ที่คุณเปิดอาจถูกลบ หรือพิมพ์โค้ดไม่ถูกต้อง');
}

if ($reason = link_unavailable_reason($link)) {
    show_notice('⛔', 'ลิงก์ใช้งานไม่ได้', $reason, 410);
}

/* ---- ลิงก์ที่ล็อกด้วยรหัสผ่าน ---- */
if (!empty($link['password_hash'])) {
    $unlocked = !empty($_SESSION['unlocked'][$link['id']]);
    $err = null;

    if (!$unlocked && $_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $tries = (int)($_SESSION['pw_tries'][$link['id']] ?? 0);

        if ($tries >= 10) {
            $err = 'ใส่รหัสผ่านผิดหลายครั้งเกินไป กรุณาลองใหม่ภายหลัง';
            sleep(2);
        } elseif (password_verify((string)($_POST['password'] ?? ''), $link['password_hash'])) {
            $_SESSION['unlocked'][$link['id']] = true;
            unset($_SESSION['pw_tries'][$link['id']]);
            $unlocked = true;
        } else {
            $_SESSION['pw_tries'][$link['id']] = $tries + 1;
            $err = 'รหัสผ่านไม่ถูกต้อง';
            usleep(400000); // หน่วงเวลาให้การเดาแบบอัตโนมัติช้าลง
        }
    }

    if (!$unlocked) {
        page_head('ลิงก์นี้ต้องใช้รหัสผ่าน');
        ?>
        <div class="auth-wrap">
          <form method="post" class="card card-pad auth-card stack">
            <div class="center">
              <div style="font-size:2.4rem">🔒</div>
              <h2>ลิงก์นี้ถูกล็อกไว้</h2>
              <p class="muted small">กรุณากรอกรหัสผ่านเพื่อเข้าถึงปลายทาง</p>
            </div>
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <?php if ($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>
            <div class="field">
              <label for="password">รหัสผ่าน</label>
              <input class="input" id="password" name="password" type="password" autofocus required>
            </div>
            <button class="btn btn-block" type="submit">เปิดลิงก์</button>
          </form>
        </div>
        <?php
        page_foot();
        exit;
    }
}

record_click($link);

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Location: ' . $link['target_url'], true, 302);
exit;
