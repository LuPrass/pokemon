<?php
    session_start();

// Verifica se a sessão 'logado' existe e é verdadeira
if (!isset($_SESSION['logado']) || $_SESSION['logado'] !== true) {
    // Se não estiver logado, redireciona para a página de login
    header('Location: login.php');
    exit;
}
