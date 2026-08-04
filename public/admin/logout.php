<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

auth_logout();
header('Location: login.php');
exit;
