<?php
require_once __DIR__ . '/functions.php';
logoutUser();
header("Location: " . BASE_URL . "/login.php");
exit;
?>