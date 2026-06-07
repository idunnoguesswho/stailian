// admin_check.php
<?php
require 'auth.php'; // ensures logged in
if (empty($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: walk.php?msg=Access+denied&type=error');
    exit();
}