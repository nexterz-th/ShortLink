<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

$user = require_login();
$id   = (int)($_GET['id'] ?? 0);
$link = $id > 0 ? find_link_by_id($id) : null;

if ($id > 0 && !$link) {
    http_response_code(404);
    exit('ไม่พบลิงก์');
}
if ($link && $user['role'] !== 'admin' && (int)$link['user_id'] !== (int)$user['id']) {
    http_response_code(403);
    exit('คุณไม่มีสิทธิ์แก้ไขลิงก์นี้');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    /* ---- ลบลิงก์ ---- */
    if (($_POST['action'] ?? '') === 'delete' && $link) {
        db()->prepare("DELETE FROM links WHERE id = ?")->execute([$link['id']]);
        flash('ลบลิงก์ /' . $link['code'] . ' แล้ว');
        header('Location: links.php');
        exit;
    }

    $data = [
        'url'        => $_POST['url'] ?? '',
        'code'       => trim((string)($_POST['code'] ?? '')),
        'title'      => $_POST['title'] ?? '',
        'note'       => $_POST['note'] ?? '',
        'password'   => (string)($_POST['password'] ?? ''),
        'expires_at' => $_POST['expires_at'] ?? '',
        'max_clicks' => (int)($_POST['max_clicks'] ?? 0),
        'is_active'  => isset($_POST['is_active']) ? 1 : 0,
    ];

    if (!$link) {
        $res = create_link($data, (int)$user['id']);
        if ($res['ok']) {
            flash('สร้างลิงก์ /' . $res['link']['code'] . ' เรียบร้อยแล้ว');
            header('Location: link-stats.php?id=' . $res['link']['id']);
            exit;
        }
        $errors[] = $res['error'];
    } else {
        /* ---- อัปเดต ---- */
        $err = null;
        $url = normalize_url((string)$data['url'], $err);
        if ($url === null) {
            $errors[] = $err;
        }
        if ($data['code'] !== $link['code']) {
            if ($msg = validate_code($data['code'])) {
                $errors[] = $msg;
            }
        }
        $expires = null;
        if (trim((string)$data['expires_at']) !== '') {
            $ts = strtotime((string)$data['expires_at']);
            if ($ts === false) {
                $errors[] = 'รูปแบบวันหมดอายุไม่ถูกต้อง';
            } else {
                $expires = date('Y-m-d H:i:s', $ts);
            }
        }

        if (!$errors) {
            $hash = $link['password_hash'];
            if (($_POST['clear_password'] ?? '') === '1') {
                $hash = null;
            } elseif ($data['password'] !== '') {
                $hash = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            db()->prepare("UPDATE links SET code = ?, target_url = ?, title = ?, note = ?, password_hash = ?,
                           expires_at = ?, max_clicks = ?, is_active = ? WHERE id = ?")
                ->execute([
                    $data['code'], $url,
                    mb_substr(trim((string)$data['title']), 0, 255),
                    mb_substr(trim((string)$data['note']), 0, 255),
                    $hash, $expires,
                    $data['max_clicks'] > 0 ? $data['max_clicks'] : null,
                    $data['is_active'], $link['id'],
                ]);
            flash('บันทึกการเปลี่ยนแปลงแล้ว');
            header('Location: link-edit.php?id=' . $link['id']);
            exit;
        }
    }

    // ใส่ค่าที่กรอกกลับเข้าฟอร์มเมื่อมีข้อผิดพลาด
    $link = array_merge($link ?? [
        'id' => 0, 'clicks' => 0, 'password_hash' => null, 'created_at' => date('Y-m-d H:i:s'),
    ], [
        'code' => $data['code'], 'target_url' => $data['url'], 'title' => $data['title'],
        'note' => $data['note'], 'expires_at' => $data['expires_at'] ?: null,
        'max_clicks' => $data['max_clicks'] ?: null, 'is_active' => $data['is_active'],
    ]);
}

$isNew = empty($link['id']);
$v = function (string $k, $default = '') use ($link) { return $link[$k] ?? $default; };

admin_start($isNew ? 'สร้างลิงก์ใหม่' : 'แก้ไขลิงก์', $isNew ? 'new' : 'links');
flash_render();
?>
<?php foreach ($errors as $err): ?>
  <div class="alert alert-error">⚠️ <span><?= e($err) ?></span></div>
<?php endforeach; ?>

<form method="post" style="display:grid;grid-template-columns:minmax(0,2fr) minmax(260px,1fr);gap:22px;align-items:start">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

  <div class="card card-pad stack">
    <div class="field">
      <label for="url">ลิงก์ปลายทาง *</label>
      <input class="input" id="url" name="url" required placeholder="https://example.com/page" value="<?= e((string)$v('target_url')) ?>">
    </div>
    <div class="field">
      <label for="code">โค้ดลิงก์ย่อ</label>
      <div class="row">
        <span class="muted small nowrap"><?= e(base_url()) ?>/</span>
        <input class="input grow mono" id="code" name="code" placeholder="เว้นว่างเพื่อสุ่ม" value="<?= e((string)$v('code')) ?>">
      </div>
      <span class="help">ใช้ได้เฉพาะ a-z A-Z 0-9 _ - (3–64 ตัวอักษร)</span>
    </div>
    <div class="field">
      <label for="title">ชื่อเรียกลิงก์</label>
      <input class="input" id="title" name="title" value="<?= e((string)$v('title')) ?>" placeholder="แคมเปญโฆษณา Facebook">
    </div>
    <div class="field">
      <label for="note">บันทึกภายใน</label>
      <textarea class="input" id="note" name="note" placeholder="ข้อความช่วยจำ ไม่แสดงต่อผู้ใช้ทั่วไป"><?= e((string)$v('note')) ?></textarea>
    </div>
  </div>

  <div class="stack">
    <div class="card card-pad stack">
      <h3 style="margin:0">ตัวเลือก</h3>
      <label class="switch">
        <input type="checkbox" name="is_active" value="1" <?= (int)$v('is_active', 1) === 1 ? 'checked' : '' ?>>
        <span class="track"></span><span>เปิดใช้งานลิงก์</span>
      </label>
      <div class="field">
        <label for="expires_at">วันหมดอายุ</label>
        <input class="input" id="expires_at" name="expires_at" type="datetime-local"
               value="<?= e($v('expires_at') ? date('Y-m-d\TH:i', strtotime((string)$v('expires_at'))) : '') ?>">
      </div>
      <div class="field">
        <label for="max_clicks">จำกัดจำนวนคลิก</label>
        <input class="input" id="max_clicks" name="max_clicks" type="number" min="0" value="<?= e((string)($v('max_clicks') ?: '')) ?>" placeholder="0 = ไม่จำกัด">
      </div>
      <div class="field">
        <label for="password">รหัสผ่านลิงก์</label>
        <input class="input" id="password" name="password" type="text" placeholder="<?= $v('password_hash') ? 'ตั้งค่าไว้แล้ว — กรอกเพื่อเปลี่ยน' : 'เว้นว่าง = ไม่ใช้รหัสผ่าน' ?>">
        <?php if ($v('password_hash')): ?>
          <label class="switch" style="margin-top:6px">
            <input type="checkbox" name="clear_password" value="1"><span class="track"></span><span class="small">ยกเลิกรหัสผ่าน</span>
          </label>
        <?php endif; ?>
      </div>
      <button class="btn btn-block" type="submit"><?= $isNew ? 'สร้างลิงก์' : 'บันทึกการแก้ไข' ?></button>
    </div>

    <?php if (!$isNew): ?>
      <div class="card card-pad stack">
        <div class="row-between">
          <span class="muted small">ลิงก์ย่อ</span>
          <button type="button" class="btn btn-ghost btn-sm" data-copy="<?= e(short_url((string)$v('code'))) ?>">คัดลอก</button>
        </div>
        <a class="mono" href="<?= e(short_url((string)$v('code'))) ?>" target="_blank" rel="noopener"><?= e(short_url((string)$v('code'))) ?></a>
        <div class="row-between small"><span class="muted">คลิกทั้งหมด</span><b><?= number_format((int)$v('clicks', 0)) ?></b></div>
        <div class="row-between small"><span class="muted">สร้างเมื่อ</span><span><?= e(date('d/m/Y H:i', strtotime((string)$v('created_at')))) ?></span></div>
        <a class="btn btn-ghost btn-block btn-sm" href="link-stats.php?id=<?= (int)$v('id') ?>">ดูสถิติละเอียด</a>
      </div>
    <?php endif; ?>
  </div>
</form>

<?php if (!$isNew): ?>
  <form method="post" data-confirm="ต้องการลบลิงก์นี้และประวัติการคลิกทั้งหมดหรือไม่?">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="delete">
    <button class="btn btn-danger btn-sm" type="submit">🗑 ลบลิงก์นี้</button>
  </form>
<?php endif; ?>
<?php admin_end(); ?>
