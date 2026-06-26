<?php
require_once __DIR__ . '/config.php';
startUserSession();
clearRememberMeToken();  // پاک کردن کوکی remember me و توکن دیتابیس
$_SESSION = [];
session_destroy();
header('Location: /physics_academy/');
exit;
