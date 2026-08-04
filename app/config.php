<?php
/**
 * โหลดค่าคอนฟิกหลักของระบบ
 * ค่าจริงอยู่ในไฟล์ app/config.local.php (สร้างโดย install.php)
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_DIR', __DIR__);

$defaults = [
    'db_host' => 'localhost',
    'db_port' => 3306,
    'db_name' => '',
    'db_user' => '',
    'db_pass' => '',
    'base_url' => '',
    'site_name' => 'Link.',
    'timezone'  => 'Asia/Bangkok',
];

$local = APP_DIR . '/config.local.php';
$config = is_file($local) ? array_merge($defaults, require $local) : $defaults;

define('APP_INSTALLED', is_file($local));
define('CFG', $config);

date_default_timezone_set($config['timezone']);
mb_internal_encoding('UTF-8');
