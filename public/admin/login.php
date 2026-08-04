<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';
require APP_DIR . '/layout.php';

if (auth_user()) {
    header('Location: index.php');
    exit;
}

$error = null;
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (login_locked()) {
        $error = 'พยายามเข้าสู่ระบบผิดหลายครั้งเกินไป กรุณารอ ' . LOGIN_WINDOW_MIN . ' นาทีแล้วลองใหม่';
        sleep(2);
    } elseif (auth_attempt($username, $password)) {
        $next = (string)($_GET['next'] ?? 'index.php');
        header('Location: ' . (preg_match('~^/?[A-Za-z0-9_./-]*$~', $next) ? $next : 'index.php'));
        exit;
    } else {
        login_record_fail($username);
        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        usleep(400000);
    }
}

page_head('เข้าสู่ระบบ');
?>
<div class="auth-wrap">
  <form method="post" class="auth-card stack">
    <div class="center">
      <div class="brand" style="justify-content:center"><span class="dot">🔗</span><?= e((string)setting('site_name', 'Link.')) ?></div>
      <p class="muted small" style="margin-top:6px">เข้าสู่ระบบเพื่อจัดการลิงก์ของคุณ</p>
    </div>

    <div class="card card-pad stack">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <?php if ($error): ?><div class="alert alert-error">⚠️ <span><?= e($error) ?></span></div><?php endif; ?>
      <div class="field">
        <label for="username">ชื่อผู้ใช้</label>
        <input class="input" id="username" name="username" value="<?= e($username) ?>" autofocus required autocomplete="username">
      </div>
      <div class="field">
        <label for="password">รหัสผ่าน</label>
        <input class="input" id="password" name="password" type="password" required autocomplete="current-password">
      </div>
      <button class="btn btn-block" type="submit">เข้าสู่ระบบ</button>
    </div>

    <div class="center small"><a href="/">← กลับหน้าแรก</a></div>
    <div class="center credit faint" style="justify-content:center">Made with <span class="heart">❤️</span> by <b>NEXTERZ</b></div>
  </form>
</div>
<?php page_foot(); ?>
