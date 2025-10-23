<?php
function getDBConnection() {
    try {
        $db = new PDO('sqlite:' . __DIR__ . '/../database.sqlite');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    } catch (PDOException $e) {
        die("Veritabanına bağlanılamadı: " . $e->getMessage());
    }
}
?>