<?php
declare(strict_types=1);

/** ส่วนหัวของหน้า (ใช้ร่วมกันทุกหน้า) */
function page_head(string $title, string $bodyClass = '', string $assets = 'assets'): void
{
    $site = APP_INSTALLED ? (string)setting('site_name', 'Link.') : 'Link.';
    ?><!DOCTYPE html>
<html lang="th" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · <?= e($site) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@100;200;300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/<?= $assets ?>/css/app.css">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔗</text></svg>">
<script>document.documentElement.setAttribute('data-theme', localStorage.getItem('link-theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));</script>
</head>
<body class="<?= e($bodyClass) ?>">
<?php
}

function page_foot(string $assets = 'assets'): void
{
    ?>
<script src="/<?= $assets ?>/js/app.js"></script>
</body>
</html>
<?php
}

/** ส่วนท้ายเว็บ ใช้ร่วมกันทุกหน้าสาธารณะ */
function site_footer(): void
{
    ?>
<footer class="footer">
  <div class="container footer-inner">
    <span class="credit">Made with <span class="heart">❤️</span> by <b>NEXTERZ</b></span>
  </div>
</footer>
<?php
}

function theme_button(): void
{
    ?><button class="btn btn-ghost btn-icon" data-theme-toggle title="สลับโหมดสว่าง/มืด"><span data-theme-icon>🌙</span></button><?php
}
