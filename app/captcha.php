<?php
declare(strict_types=1);

/**
 * ระบบกัน bot สำหรับฟอร์มสาธารณะ ประกอบด้วย 3 ชั้น
 *   1. CAPTCHA รูปภาพ (ใช้ GD ถ้าไม่มีจะถอยไปใช้โจทย์เลขเป็นข้อความ)
 *   2. ช่อง honeypot ที่ซ่อนไว้ — bot ส่วนใหญ่จะกรอก
 *   3. ดักเวลาส่งฟอร์มที่เร็วผิดปกติ
 */

const CAPTCHA_TTL      = 600;  // อายุของโจทย์ (วินาที)
const CAPTCHA_MIN_TIME = 2;    // เวลาขั้นต่ำที่คนจริงใช้กรอกฟอร์ม (วินาที)

function captcha_enabled(): bool
{
    $mode = (string)setting('captcha_mode', 'guest');
    if ($mode === 'off') {
        return false;
    }
    if ($mode === 'all') {
        return true;
    }
    return auth_user() === null; // 'guest' — บังคับเฉพาะผู้ที่ไม่ได้เข้าสู่ระบบ
}

function captcha_has_gd(): bool
{
    return function_exists('imagecreatetruecolor') && function_exists('imagepng') && function_exists('imagerotate');
}

/** สร้างโจทย์ใหม่และเก็บคำตอบไว้ในเซสชัน คืนค่าโจทย์ที่ต้องแสดง (กรณีข้อความ) */
function captcha_new(): string
{
    if (captcha_has_gd()) {
        // ตัดตัวอักษรที่สับสนง่ายออก (0/O, 1/I/l)
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $text = '';
        for ($i = 0; $i < 5; $i++) {
            $text .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $answer = $text;
        $question = '';
    } else {
        $a = random_int(2, 9);
        $b = random_int(2, 9);
        $answer   = (string)($a + $b);
        $question = "$a + $b = ?";
    }

    $_SESSION['captcha'] = [
        'answer'   => strtoupper($answer),
        'question' => $question,
        'exp'      => time() + CAPTCHA_TTL,
        'tries'    => 0,
    ];
    return $question;
}

/** ข้อความโจทย์ปัจจุบัน (สร้างใหม่ถ้ายังไม่มีหรือหมดอายุ) */
function captcha_question(): string
{
    $c = $_SESSION['captcha'] ?? null;
    if (!$c || $c['exp'] < time()) {
        return captcha_new();
    }
    return (string)$c['question'];
}

/**
 * ตรวจคำตอบ — ใช้ได้ครั้งเดียวต่อโจทย์
 * @return bool ผ่านหรือไม่
 */
function captcha_verify(string $input): bool
{
    $c = $_SESSION['captcha'] ?? null;
    unset($_SESSION['captcha']); // ใช้แล้วทิ้งเสมอ กันการส่งซ้ำ

    if (!$c || $c['exp'] < time()) {
        return false;
    }
    return hash_equals($c['answer'], strtoupper(trim($input)));
}

/** ตรวจกับดัก honeypot และเวลา — คืนข้อความผิดพลาด หรือ null ถ้าผ่าน */
function bot_trap_check(): ?string
{
    if (trim((string)($_POST['website'] ?? '')) !== '') {
        return 'ตรวจพบการส่งข้อมูลอัตโนมัติ';
    }
    $ts = (int)($_SESSION['form_ts'] ?? 0);
    if ($ts > 0 && (time() - $ts) < CAPTCHA_MIN_TIME) {
        return 'ส่งฟอร์มเร็วเกินไป กรุณาลองอีกครั้ง';
    }
    return null;
}

/** บันทึกเวลาที่เริ่มแสดงฟอร์ม */
function bot_trap_start(): void
{
    $_SESSION['form_ts'] = time();
}

/** ช่อง honeypot ที่ซ่อนจากผู้ใช้จริง (bot มองเห็นใน HTML) */
function honeypot_field(): void
{
    ?><div class="hp" aria-hidden="true">
    <label>เว้นช่องนี้ว่างไว้</label>
    <input type="text" name="website" tabindex="-1" autocomplete="off">
  </div><?php
}

/** วาดรูป CAPTCHA เป็น PNG ออกไปที่เบราว์เซอร์ */
function captcha_render_png(string $text): void
{
    $w = 170;
    $h = 56;
    $img = imagecreatetruecolor($w, $h);
    imageantialias($img, true);

    $bg = imagecolorallocate($img, 255, 255, 255);
    imagefilledrectangle($img, 0, 0, $w, $h, $bg);

    // เส้นรบกวนพื้นหลัง
    for ($i = 0; $i < 6; $i++) {
        $c = imagecolorallocate($img, random_int(200, 235), random_int(200, 235), random_int(230, 250));
        imagesetthickness($img, random_int(1, 2));
        imageline($img, 0, random_int(0, $h), $w, random_int(0, $h), $c);
    }
    // จุดรบกวน
    for ($i = 0; $i < 320; $i++) {
        $c = imagecolorallocate($img, random_int(180, 230), random_int(180, 230), random_int(200, 240));
        imagesetpixel($img, random_int(0, $w), random_int(0, $h), $c);
    }

    $font = APP_DIR . '/fonts/captcha.ttf';
    $hasTtf = function_exists('imagettftext') && is_file($font);
    $len  = strlen($text);
    $step = (int)(($w - 30) / max(1, $len));

    for ($i = 0; $i < $len; $i++) {
        $c = imagecolorallocate($img, random_int(30, 90), random_int(30, 90), random_int(90, 170));
        $x = 18 + $i * $step;
        $angle = random_int(-24, 24);

        if ($hasTtf) {
            imagettftext($img, random_int(22, 27), $angle, $x, random_int(38, 46), $c, $font, $text[$i]);
            continue;
        }

        // ไม่มีฟอนต์ TTF — วาดตัวอักษรลงภาพย่อยแล้วหมุน เพื่อให้บิดเบี้ยวพอกัน OCR อย่างง่าย
        $cw = imagefontwidth(5);
        $ch = imagefontheight(5);
        $glyph = imagecreatetruecolor($cw, $ch);
        $gbg = imagecolorallocate($glyph, 255, 255, 255);
        imagefilledrectangle($glyph, 0, 0, $cw, $ch, $gbg);
        $gc = imagecolorallocate($glyph, random_int(30, 90), random_int(30, 90), random_int(90, 170));
        imagestring($glyph, 5, 0, 0, $text[$i], $gc);
        $glyph = imagescale($glyph, (int)($cw * 2.4), (int)($ch * 2.1));
        $gbg = imagecolorallocate($glyph, 255, 255, 255);
        $glyph = imagerotate($glyph, $angle, $gbg);
        imagecolortransparent($glyph, $gbg);
        imagecopy($img, $glyph, $x, random_int(6, 16), 0, 0, imagesx($glyph), imagesy($glyph));
        imagedestroy($glyph);
    }
    imagesetthickness($img, 2);
    $line = imagecolorallocate($img, 120, 120, 190);
    imageline($img, 0, random_int(18, 38), $w, random_int(18, 38), $line);

    header('Content-Type: image/png');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    imagepng($img);
    imagedestroy($img);
}
