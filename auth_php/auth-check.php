<?php
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: /login_php/login.php');
    exit;
}
