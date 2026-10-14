<?php
require_once '../../config/database.php';
require_once '../../config/settings.php';
require_once '../../config/auth.php';
session_start();
$_SESSION['user_id']=1;
$_SESSION['user_role']='admin';
header('Content-Type: text/html; charset=utf-8');
echo getSetting('store_name','X')."<br>";
echo htmlspecialchars(getSetting('receipt_header','X'))."<br>";
echo getSetting('store_phone','X')."<br>";
?>