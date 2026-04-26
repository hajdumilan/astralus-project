<?php
session_start();

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

logoutUser($pdo);

header('Location: /login.php');
exit;
