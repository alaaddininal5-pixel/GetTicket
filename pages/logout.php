<?php
session_start();

// Session değişkenlerini temizle
$_SESSION = array();

// Session'ı tamamen yok et
session_destroy();

// Login sayfasına yönlendir
header("Location: login.php");
exit();
?>