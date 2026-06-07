<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['userid'])) {
    header('Location: login.php');
    exit();
}
$SESSION_USERID = (int)$_SESSION['userid'];