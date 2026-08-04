<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

$me = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (($_POST['action'] ?? '') === 'purge') {
        $days = max(1, (int)($_POST['days'] ?? 90));
        $st = db()->prepare("DELETE FROM clicks WHERE created_at < (NOW() - INTERVAL ? DAY)");
        $st->bindValue(1, $days, PDO::PARAM_INT);
        $st->execute();
        flash('ลบประวัติการคลิกที่เก่ากว่า ' . $days . ' วันแล้ว (' . $st->rowCount() . ' รายการ)');
    } else {
        setting_set('site_name', mb_substr(trim((string)($_POST['site_name'] ?? 'Link.')), 0, 60));
        setting_set('public_shorten', isset($_POST['public_shorten']) ? '1' : '0');
        setting_set('code_length', (string)max(4, min(16, (int)($_POST['code_length'] ?? 6))));
        setting_set('rate_limit', (string)max(0, (int)($_POST['rate_limit'] ?? 20)));
        $mode = (string)($_POST['captcha_mode'] ?? 'guest');
        setting_set('captcha_mode', in_array($mode, ['off', 'guest', 'all'], true) ? $mode : 'guest');
        setting_set('blocked_domains', trim((string)($_POST['blocked_domains'] ?? '')));
        flash('บันทึกการตั้งค่าแล้ว');
    }
    header('Location: settings.php');
    exit;
}

$clickCount = (int)db()->query("SELECT COUNT(*) FROM clicks")->fetchColumn();

admin_start('ตั้งค่าระบบ', 'settings');
flash_render();
?>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:22px;align-items:start">
  <form method="post" class="card card-pad stack">
    <h3 style="margin:0">การตั้งค่าทั่วไป</h3>
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <div class="field">
      <label>ชื่อเว็บไซต์</label>
      <input class="input" name="site_name" value="<?= e((string)setting('site_name', 'Link.')) ?>">
    </div>
    <label class="switch">
      <input type="checkbox" name="public_shorten" value="1" <?= setting('public_shorten', '1') === '1' ? 'checked' : '' ?>>
      <span class="track"></span><span>ให้ผู้เยี่ยมชมทั่วไปย่อลิงก์ได้</span>
    </label>
    <div class="field">
      <label>ความยาวโค้ดที่สุ่ม</label>
      <input class="input" name="code_length" type="number" min="4" max="16" value="<?= e((string)setting('code_length', '6')) ?>">
    </div>
    <div class="field">
      <label>จำกัดจำนวนลิงก์ต่อ IP ต่อชั่วโมง</label>
      <input class="input" name="rate_limit" type="number" min="0" value="<?= e((string)setting('rate_limit', '20')) ?>">
      <span class="help">ใช้กับผู้เยี่ยมชมที่ไม่ได้เข้าสู่ระบบ · 0 = ไม่จำกัด</span>
    </div>
    <div class="field">
      <label>การยืนยันตัวตนกัน bot (CAPTCHA)</label>
      <?php $cm = (string)setting('captcha_mode', 'guest'); ?>
      <select class="select" name="captcha_mode">
        <option value="guest" <?= $cm === 'guest' ? 'selected' : '' ?>>เฉพาะผู้ที่ไม่ได้เข้าสู่ระบบ (แนะนำ)</option>
        <option value="all"   <?= $cm === 'all' ? 'selected' : '' ?>>บังคับทุกคน</option>
        <option value="off"   <?= $cm === 'off' ? 'selected' : '' ?>>ปิดใช้งาน</option>
      </select>
      <span class="help">
        แสดงรหัสยืนยันบนฟอร์มย่อลิงก์หน้าแรก พร้อมกับดัก honeypot และดักการส่งฟอร์มที่เร็วผิดปกติ
        <?php if (!function_exists('imagecreatetruecolor')): ?>
          <br><b>หมายเหตุ:</b> เซิร์ฟเวอร์ไม่ได้เปิดส่วนขยาย GD ระบบจะใช้โจทย์คณิตศาสตร์แบบข้อความแทนรูปภาพ
        <?php endif; ?>
      </span>
    </div>
    <div class="field">
      <label>โดเมนที่ห้ามย่อ</label>
      <textarea class="input" name="blocked_domains" placeholder="หนึ่งโดเมนต่อบรรทัด เช่น&#10;spam.com"><?= e((string)setting('blocked_domains', '')) ?></textarea>
    </div>
    <button class="btn btn-block" type="submit">บันทึกการตั้งค่า</button>
  </form>

  <div class="stack">
    <form method="post" class="card card-pad stack" data-confirm="ยืนยันการลบประวัติการคลิกเก่า?">
      <h3 style="margin:0">ดูแลฐานข้อมูล</h3>
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="action" value="purge">
      <p class="small muted" style="margin:0">ขณะนี้มีประวัติการคลิก <b><?= number_format($clickCount) ?></b> รายการ ลบข้อมูลเก่าเพื่อให้ฐานข้อมูลเบาลงได้</p>
      <div class="field">
        <label>ลบประวัติที่เก่ากว่า (วัน)</label>
        <input class="input" name="days" type="number" min="1" value="90">
      </div>
      <button class="btn btn-danger btn-block" type="submit">ลบประวัติเก่า</button>
    </form>

    <div class="card card-pad stack">
      <h3 style="margin:0">ข้อมูลระบบ</h3>
      <div class="row-between small"><span class="muted">URL หลัก</span><span class="mono truncate"><?= e(base_url()) ?></span></div>
      <div class="row-between small"><span class="muted">PHP</span><span class="mono"><?= e(PHP_VERSION) ?></span></div>
      <div class="row-between small"><span class="muted">ฐานข้อมูล</span><span class="mono"><?= e((string)CFG['db_name']) ?></span></div>
      <div class="row-between small"><span class="muted">เขตเวลา</span><span class="mono"><?= e(date_default_timezone_get()) ?></span></div>
      <p class="help" style="margin:0">แก้ไขข้อมูลการเชื่อมต่อฐานข้อมูลได้ที่ไฟล์ <span class="mono">app/config.local.php</span></p>
    </div>
  </div>
</div>
<?php admin_end(); ?>
