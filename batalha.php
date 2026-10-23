<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/verifica_login.php';
require_once __DIR__ . '/src/Views/batalha/arena.php';
