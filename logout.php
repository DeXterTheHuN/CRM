<?php
require_once 'config.php';

// Session törlése
session_destroy();

// Átirányítás a bejelentkezési oldalra
redirect('login.php');
?>
