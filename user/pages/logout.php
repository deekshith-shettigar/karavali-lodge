<?php
require_once __DIR__ . '/../includes/config.php';
session_destroy();
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
redirect(SITE_URL . '/pages/login.php');