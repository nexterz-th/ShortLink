<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/config.php';
require APP_DIR . '/db.php';
require APP_DIR . '/helpers.php';
require APP_DIR . '/layout.php';

session_start();

$configFile = APP_DIR . '/config.local.php';
$done = false;
$errors = [];

if (APP_INSTALLED) {
    http_response_code(403);
    page_head('ติดตั้งแล้ว');
    echo '<div class="auth-wrap"><div class="card card-pad auth-card center"><div style="font-size:2.4rem">✅</div><h2>ระบบติดตั้งเรียบร้อยแล้ว</h2><p class="muted small">หากต้องการติดตั้งใหม่ ให้ลบไฟล์ <span class="mono">app/config.local.php</span> ออกก่อน</p><a class="btn btn-block" href="/admin/">ไปที่หลังบ้าน</a></div></div>';
    page_foot();
    exit;
}

$f = [
    'db_host' => $_POST['db_host'] ?? 'localhost',
    'db_port' => $_POST['db_port'] ?? '3306',
    'db_name' => $_POST['db_name'] ?? '',
    'db_user' => $_POST['db_user'] ?? '',
    'db_pass' => $_POST['db_pass'] ?? '',
    'site_name' => $_POST['site_name'] ?? 'Link.',
    'base_url' => $_POST['base_url'] ?? (($_SERVER['HTTPS'] ?? '') ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? ''),
    'admin_user' => $_POST['admin_user'] ?? 'admin',
    'admin_name' => $_POST['admin_name'] ?? 'ผู้ดูแลระบบ',
    'admin_pass' => $_POST['admin_pass'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!extension_loaded('pdo_mysql')) {
        $errors[] = 'เซิร์ฟเวอร์ไม่ได้เปิดส่วนขยาย pdo_mysql';
    }
    if ($f['db_name'] === '' || $f['db_user'] === '') {
        $errors[] = 'กรุณากรอกชื่อฐานข้อมูลและชื่อผู้ใช้ฐานข้อมูล';
    }
    if (strlen($f['admin_pass']) < 8) {
        $errors[] = 'รหัสผ่านผู้ดูแลระบบต้องยาวอย่างน้อย 8 ตัวอักษร';
    }
    if (!preg_match('/^[A-Za-z0-9_.-]{3,64}$/', $f['admin_user'])) {
        $errors[] = 'ชื่อผู้ใช้ผู้ดูแลระบบต้องเป็น a-z 0-9 _ . - ความยาว 3–64 ตัว';
    }
    if (!is_writable(APP_DIR)) {
        $errors[] = 'โฟลเดอร์ app/ เขียนไฟล์ไม่ได้ กรุณาตั้งสิทธิ์ให้เขียนได้ (755/775)';
    }

    if (!$errors) {
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $f['db_host'], (int)$f['db_port'], $f['db_name']);
            $pdo = new PDO($dsn, $f['db_user'], $f['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            db_migrate($pdo);

            // กันการติดตั้งซ้ำเพื่อยึดบัญชี: ถ้ามีผู้ใช้อยู่แล้วต้องยืนยันด้วยรหัสผ่านเดิม
            $existing = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            if ($existing > 0) {
                $st = $pdo->prepare("SELECT password_hash FROM users WHERE username = ? AND role = 'admin' LIMIT 1");
                $st->execute([$f['admin_user']]);
                $hash = (string)$st->fetchColumn();
                if ($hash === '' || !password_verify($f['admin_pass'], $hash)) {
                    throw new RuntimeException('ฐานข้อมูลนี้มีผู้ใช้อยู่แล้ว หากต้องการเชื่อมต่อใหม่ ต้องกรอกชื่อผู้ใช้และรหัสผ่านของผู้ดูแลระบบเดิมให้ถูกต้อง');
                }
            }

            $pdo->prepare("INSERT INTO users (username, password_hash, name, role) VALUES (?, ?, ?, 'admin')
                           ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), role = 'admin'")
                ->execute([$f['admin_user'], password_hash($f['admin_pass'], PASSWORD_DEFAULT), $f['admin_name']]);

            $pdo->prepare("INSERT INTO settings (k, v) VALUES ('site_name', ?) ON DUPLICATE KEY UPDATE v = VALUES(v)")
                ->execute([$f['site_name']]);

            $php = "<?php\n// ไฟล์คอนฟิกที่สร้างโดยตัวติดตั้ง " . date('Y-m-d H:i') . "\nreturn " .
                var_export([
                    'db_host'  => $f['db_host'],
                    'db_port'  => (int)$f['db_port'],
                    'db_name'  => $f['db_name'],
                    'db_user'  => $f['db_user'],
                    'db_pass'  => $f['db_pass'],
                    'base_url' => rtrim(trim($f['base_url']), '/'),
                    'site_name' => $f['site_name'],
                    'timezone' => 'Asia/Bangkok',
                ], true) . ";\n";

            if (file_put_contents($configFile, $php) === false) {
                $errors[] = 'เขียนไฟล์ app/config.local.php ไม่สำเร็จ';
            } else {
                $done = true;
            }
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        } catch (PDOException $ex) {
            $errors[] = 'เชื่อมต่อฐานข้อมูลไม่สำเร็จ: ' . $ex->getMessage();
        }
    }
}

page_head('ติดตั้งระบบ');
?>
<main class="container container-sm" style="padding-top:44px;padding-bottom:60px">
  <div class="center" style="margin-bottom:26px">
    <div style="font-size:2.4rem">🔗</div>
    <h1>ติดตั้งระบบย่อลิงก์</h1>
    <p class="muted">กรอกข้อมูลฐานข้อมูลจาก Plesk แล้วกดติดตั้ง ใช้เวลาไม่ถึงหนึ่งนาที</p>
  </div>

  <?php if ($done): ?>
    <div class="card card-pad stack">
      <div class="alert alert-success">✅ ติดตั้งสำเร็จแล้ว</div>
      <p class="small">เพื่อความปลอดภัย กรุณา <b>ลบไฟล์ <span class="mono">install.php</span></b> ออกจากเซิร์ฟเวอร์</p>
      <a class="btn btn-block" href="/admin/login.php">เข้าสู่ระบบหลังบ้าน</a>
    </div>
  <?php else: ?>
    <form method="post" class="card card-pad stack">
      <?php foreach ($errors as $err): ?>
        <div class="alert alert-error">⚠️ <span><?= e($err) ?></span></div>
      <?php endforeach; ?>

      <h3>ฐานข้อมูล MySQL</h3>
      <div class="adv-grid">
        <div class="field"><label>โฮสต์</label><input class="input" name="db_host" value="<?= e($f['db_host']) ?>" required></div>
        <div class="field"><label>พอร์ต</label><input class="input" name="db_port" value="<?= e($f['db_port']) ?>"></div>
        <div class="field"><label>ชื่อฐานข้อมูล</label><input class="input" name="db_name" value="<?= e($f['db_name']) ?>" required></div>
        <div class="field"><label>ผู้ใช้ฐานข้อมูล</label><input class="input" name="db_user" value="<?= e($f['db_user']) ?>" required></div>
        <div class="field"><label>รหัสผ่านฐานข้อมูล</label><input class="input" name="db_pass" type="text" value="<?= e($f['db_pass']) ?>"></div>
      </div>

      <h3 style="margin-top:10px">ข้อมูลเว็บไซต์</h3>
      <div class="adv-grid">
        <div class="field"><label>ชื่อเว็บไซต์</label><input class="input" name="site_name" value="<?= e($f['site_name']) ?>"></div>
        <div class="field"><label>URL หลัก</label><input class="input" name="base_url" value="<?= e($f['base_url']) ?>"><span class="help">เช่น https://sho.rt</span></div>
      </div>

      <h3 style="margin-top:10px">บัญชีผู้ดูแลระบบ</h3>
      <div class="adv-grid">
        <div class="field"><label>ชื่อผู้ใช้</label><input class="input" name="admin_user" value="<?= e($f['admin_user']) ?>" required></div>
        <div class="field"><label>ชื่อที่แสดง</label><input class="input" name="admin_name" value="<?= e($f['admin_name']) ?>"></div>
        <div class="field"><label>รหัสผ่าน</label><input class="input" name="admin_pass" type="password" required minlength="8"><span class="help">อย่างน้อย 8 ตัวอักษร</span></div>
      </div>

      <button class="btn btn-lg btn-block" type="submit">เริ่มติดตั้ง</button>
    </form>
  <?php endif; ?>
</main>
<?php page_foot(); ?>
