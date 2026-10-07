<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

logout();
header('Location: ' . BASE_URL . '/index.php');
exit;
