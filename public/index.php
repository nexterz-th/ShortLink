<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require APP_DIR . '/links.php';
require APP_DIR . '/captcha.php';
require APP_DIR . '/layout.php';

$result = null;
$error  = null;
$user   = auth_user();
$publicShorten = setting('public_shorten', '1') === '1';
$needCaptcha   = captcha_enabled();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!$publicShorten && !$user) {
        $error = 'ขณะนี้เปิดให้เฉพาะสมาชิกสร้างลิงก์ย่อเท่านั้น';
    } elseif ($needCaptcha && ($trap = bot_trap_check()) !== null) {
        $error = $trap;
    } elseif ($needCaptcha && !captcha_verify((string)($_POST['captcha'] ?? ''))) {
        $error = 'รหัสยืนยันไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    } elseif (!$user && rate_limited()) {
        $error = 'คุณสร้างลิงก์บ่อยเกินไป กรุณารอสักครู่แล้วลองใหม่';
    } else {
        $res = create_link([
            'url'      => $_POST['url'] ?? '',
            'code'     => $_POST['code'] ?? '',
            'title'    => $_POST['title'] ?? '',
            'password' => $_POST['password'] ?? '',
            'expires_at' => $_POST['expires_at'] ?? '',
        ], $user ? (int)$user['id'] : null);
        if ($res['ok']) {
            $result = $res['link'];
        } else {
            $error = $res['error'];
        }
    }
}

$stats = dashboard_stats();
$siteName = (string)setting('site_name', 'Link.');

// เตรียมโจทย์ใหม่และจับเวลาการกรอกฟอร์มทุกครั้งที่แสดงหน้า
$captchaQuestion = $needCaptcha ? captcha_question() : '';
if ($needCaptcha) {
    bot_trap_start();
}

page_head('ย่อลิงก์ให้สั้น สวย และวัดผลได้');
?>
<header class="topbar">
  <div class="container inner">
    <a class="brand" href="/"><span class="dot">🔗</span><?= e($siteName) ?></a>
    <div class="row">
      <?php theme_button(); ?>
      <?php if ($user): ?>
        <a class="btn btn-soft btn-sm" href="/admin/">แผงควบคุม</a>
      <?php else: ?>
        <a class="btn btn-ghost btn-sm" href="/admin/login.php">เข้าสู่ระบบ</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<main class="container">
  <section class="hero center">
    <h1>ย่อลิงก์ยาว ๆ ให้เหลือ<br><span class="grad">แค่คลิกเดียว</span></h1>
    <p class="lead">สร้างลิงก์สั้นพร้อมกำหนดชื่อเอง ตั้งรหัสผ่าน วันหมดอายุ และดูสถิติการคลิกแบบละเอียด</p>
  </section>

  <section class="hero-form">
    <form method="post" class="shortener">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <div class="main-row">
        <input class="input grow" type="text" name="url" inputmode="url" required
               placeholder="วางลิงก์ที่ต้องการย่อ เช่น https://example.com/บทความ-ยาว-มาก"
               value="<?= e($_POST['url'] ?? '') ?>" <?= (!$publicShorten && !$user) ? 'disabled' : '' ?>>
        <button class="btn btn-lg" type="submit" <?= (!$publicShorten && !$user) ? 'disabled' : '' ?>>ย่อลิงก์</button>
      </div>

      <div class="adv-toggle">
        <a href="#" class="small muted" data-toggle-target="#adv">⚙️ ตัวเลือกขั้นสูง</a>
      </div>

      <div id="adv" class="adv hide">
        <div class="adv-grid">
          <div class="field">
            <label for="code">โค้ดที่ต้องการ</label>
            <input class="input" id="code" name="code" placeholder="เช่น promo2026" value="<?= e($_POST['code'] ?? '') ?>">
            <span class="help">เว้นว่างเพื่อให้ระบบสุ่มให้</span>
          </div>
          <div class="field">
            <label for="title">ชื่อเรียกลิงก์</label>
            <input class="input" id="title" name="title" placeholder="โปรโมชันเดือนสิงหาคม" value="<?= e($_POST['title'] ?? '') ?>">
          </div>
          <div class="field">
            <label for="password">รหัสผ่าน</label>
            <input class="input" id="password" name="password" type="text" placeholder="เว้นว่าง = ไม่ต้องใส่รหัส">
          </div>
          <div class="field">
            <label for="expires_at">วันหมดอายุ</label>
            <input class="input" id="expires_at" name="expires_at" type="datetime-local">
          </div>
        </div>
      </div>

      <?php if ($needCaptcha): ?>
        <?php honeypot_field(); ?>
        <div class="captcha">
          <?php if (captcha_has_gd()): ?>
            <img class="captcha-img" id="captchaImg" src="/captcha.php" width="170" height="56" alt="รหัสยืนยัน">
            <button type="button" class="btn btn-ghost btn-icon" id="captchaReload" title="สุ่มรหัสใหม่">↻</button>
          <?php else: ?>
            <span class="captcha-text"><?= e($captchaQuestion) ?></span>
          <?php endif; ?>
          <input class="input captcha-input" name="captcha" required autocomplete="off" inputmode="text"
                 placeholder="กรอกรหัสที่เห็น" aria-label="รหัสยืนยันว่าไม่ใช่บอต">
        </div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert alert-error" style="margin-top:16px">⚠️ <span><?= e($error) ?></span></div>
      <?php endif; ?>

      <?php if ($result): ?>
        <?php $su = short_url($result['code']); ?>
        <div class="result">
          <div class="grow">
            <div class="faint">ลิงก์ย่อของคุณพร้อมใช้งานแล้ว</div>
            <a class="link" href="<?= e($su) ?>" target="_blank" rel="noopener"><?= e($su) ?></a>
          </div>
          <button type="button" class="btn btn-sm" data-copy="<?= e($su) ?>">คัดลอก</button>
          <a class="btn btn-ghost btn-sm" href="/p/<?= e($result['code']) ?>">ดูสถิติ</a>
        </div>
      <?php endif; ?>
    </form>
  </section>

  <section class="section">
    <div class="section-head center">
      <h2>ทุกอย่างที่ต้องใช้ อยู่ในที่เดียว</h2>
      <p class="muted">ตั้งแต่ย่อลิงก์ไปจนถึงวัดผล จัดการได้ครบจากหลังบ้านเดียว</p>
    </div>
    <div class="features">
    <div class="feature">
      <div class="ico">📊</div>
      <h3>สถิติแบบละเอียด</h3>
      <p>ดูยอดคลิกรายวัน อุปกรณ์ เบราว์เซอร์ ระบบปฏิบัติการ และแหล่งที่มาของผู้เข้าชม</p>
    </div>
    <div class="feature">
      <div class="ico">🔒</div>
      <h3>ล็อกด้วยรหัสผ่าน</h3>
      <p>ตั้งรหัสผ่านให้ลิงก์ กำหนดวันหมดอายุ หรือจำกัดจำนวนคลิกสูงสุดได้</p>
    </div>
    <div class="feature">
      <div class="ico">✏️</div>
      <h3>ตั้งชื่อโค้ดเอง</h3>
      <p>เลือกโค้ดที่จำง่ายและตรงกับแบรนด์ พร้อมแก้ไขปลายทางได้ภายหลังโดยลิงก์ไม่เปลี่ยน</p>
    </div>
    <div class="feature">
      <div class="ico">⚡</div>
      <h3>เปลี่ยนเส้นทางเร็ว</h3>
      <p>ทำงานบน PHP + MySQL ล้วน ไม่มี dependency ติดตั้งบน Plesk ได้ทันที</p>
    </div>
  </section>

  <section class="section">
    <div class="section-head center">
      <h2>ตัวเลขของระบบ</h2>
      <p class="muted">ข้อมูลรวมทั้งหมดที่เกิดขึ้นบนเว็บนี้</p>
    </div>
    <div class="stat-grid stat-grid-center">
      <div class="stat"><div class="label">ลิงก์ทั้งหมด</div><div class="value"><?= number_format($stats['links']) ?></div></div>
      <div class="stat"><div class="label">คลิกสะสม</div><div class="value"><?= number_format($stats['clicks']) ?></div></div>
      <div class="stat"><div class="label">คลิกวันนี้</div><div class="value"><?= number_format($stats['clicks_today']) ?></div></div>
      <div class="stat"><div class="label">ลิงก์ใหม่วันนี้</div><div class="value"><?= number_format($stats['links_today']) ?></div></div>
    </div>
  </section>
</main>

<?php site_footer(); ?>
<?php page_foot(); ?>
