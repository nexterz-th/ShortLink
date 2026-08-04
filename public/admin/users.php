<?php
declare(strict_types=1);

require __DIR__ . '/_layout.php';

$me = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'create') {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $name     = trim((string)($_POST['name'] ?? ''));
        $role     = ($_POST['role'] ?? 'editor') === 'admin' ? 'admin' : 'editor';

        if (!preg_match('/^[A-Za-z0-9_.-]{3,64}$/', $username)) {
            flash('ชื่อผู้ใช้ต้องเป็น a-z 0-9 _ . - ความยาว 3–64 ตัว', 'error');
        } elseif (strlen($password) < 8) {
            flash('รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร', 'error');
        } else {
            try {
                db()->prepare("INSERT INTO users (username, password_hash, name, role) VALUES (?, ?, ?, ?)")
                    ->execute([$username, password_hash($password, PASSWORD_DEFAULT), $name, $role]);
                flash('เพิ่มผู้ใช้ ' . $username . ' แล้ว');
            } catch (PDOException $ex) {
                flash('ชื่อผู้ใช้นี้ถูกใช้ไปแล้ว', 'error');
            }
        }
    } elseif ($action === 'password') {
        $uid = (int)($_POST['id'] ?? 0);
        $password = (string)($_POST['password'] ?? '');
        if (strlen($password) < 8) {
            flash('รหัสผ่านต้องยาวอย่างน้อย 8 ตัวอักษร', 'error');
        } else {
            db()->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                ->execute([password_hash($password, PASSWORD_DEFAULT), $uid]);
            flash('เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');
        }
    } elseif ($action === 'toggle') {
        $uid = (int)($_POST['id'] ?? 0);
        if ($uid === (int)$me['id']) {
            flash('ไม่สามารถปิดบัญชีของตัวเองได้', 'error');
        } else {
            db()->prepare("UPDATE users SET is_active = 1 - is_active WHERE id = ?")->execute([$uid]);
            flash('อัปเดตสถานะผู้ใช้แล้ว');
        }
    } elseif ($action === 'delete') {
        $uid = (int)($_POST['id'] ?? 0);
        if ($uid === (int)$me['id']) {
            flash('ไม่สามารถลบบัญชีของตัวเองได้', 'error');
        } else {
            db()->prepare("UPDATE links SET user_id = NULL WHERE user_id = ?")->execute([$uid]);
            db()->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
            flash('ลบผู้ใช้แล้ว (ลิงก์ของผู้ใช้ยังคงอยู่)');
        }
    }
    header('Location: users.php');
    exit;
}

$users = db()->query("SELECT u.*, (SELECT COUNT(*) FROM links l WHERE l.user_id = u.id) AS link_count
                      FROM users u ORDER BY u.id ASC")->fetchAll();

admin_start('ผู้ใช้งาน', 'users');
flash_render();
?>
<div style="display:grid;grid-template-columns:minmax(0,2fr) minmax(280px,1fr);gap:22px;align-items:start">
  <div class="card">
    <div class="card-head"><h3>รายชื่อผู้ใช้</h3><span class="badge"><?= count($users) ?> คน</span></div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>ผู้ใช้</th><th>สิทธิ์</th><th class="right">ลิงก์</th><th>เข้าใช้ล่าสุด</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td>
              <div class="row">
                <div class="avatar"><?= e(mb_substr($u['name'] !== '' ? $u['name'] : $u['username'], 0, 1)) ?></div>
                <div>
                  <div><?= e($u['name'] !== '' ? $u['name'] : $u['username']) ?>
                    <?php if (!$u['is_active']): ?><span class="badge badge-danger">ปิดใช้งาน</span><?php endif; ?>
                  </div>
                  <div class="faint mono"><?= e($u['username']) ?></div>
                </div>
              </div>
            </td>
            <td><span class="badge <?= $u['role'] === 'admin' ? 'badge-brand' : '' ?>"><?= $u['role'] === 'admin' ? 'ผู้ดูแลระบบ' : 'ผู้แก้ไข' ?></span></td>
            <td class="right"><?= number_format((int)$u['link_count']) ?></td>
            <td class="faint nowrap"><?= $u['last_login'] ? e(time_ago($u['last_login'])) : '—' ?></td>
            <td class="right nowrap">
              <form method="post" style="display:inline">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button class="btn btn-ghost btn-sm" type="submit"><?= $u['is_active'] ? 'ปิด' : 'เปิด' ?></button>
              </form>
              <form method="post" style="display:inline" data-confirm="ลบผู้ใช้ <?= e($u['username']) ?> ?">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button class="btn btn-ghost btn-sm" type="submit">ลบ</button>
              </form>
            </td>
          </tr>
          <tr>
            <td colspan="5" style="background:var(--surface-2)">
              <form method="post" class="row" style="gap:8px">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="password">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <span class="faint">ตั้งรหัสผ่านใหม่ให้ <?= e($u['username']) ?></span>
                <input class="input" name="password" type="password" placeholder="อย่างน้อย 8 ตัวอักษร" style="max-width:240px">
                <button class="btn btn-ghost btn-sm" type="submit">บันทึก</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <form method="post" class="card card-pad stack">
    <h3 style="margin:0">เพิ่มผู้ใช้ใหม่</h3>
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="create">
    <div class="field"><label>ชื่อผู้ใช้</label><input class="input" name="username" required></div>
    <div class="field"><label>ชื่อที่แสดง</label><input class="input" name="name"></div>
    <div class="field"><label>รหัสผ่าน</label><input class="input" name="password" type="password" required minlength="8"></div>
    <div class="field">
      <label>สิทธิ์การใช้งาน</label>
      <select class="select" name="role">
        <option value="editor">ผู้แก้ไข — จัดการเฉพาะลิงก์ของตัวเอง</option>
        <option value="admin">ผู้ดูแลระบบ — จัดการได้ทั้งหมด</option>
      </select>
    </div>
    <button class="btn btn-block" type="submit">เพิ่มผู้ใช้</button>
  </form>
</div>
<?php admin_end(); ?>
