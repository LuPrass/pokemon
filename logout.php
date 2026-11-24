<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/Autoload.php';

// Registra o logout no log de usuários antes de destruir a sessão
if (!empty($_SESSION['logado']) && !empty($_SESSION['usuario'])) {
    $usuarioId = $_SESSION['usuario_id'] ?? null;
    $usuarioNome = $_SESSION['usuario'] ?? 'Desconhecido';
    LogUsuario::registrar($usuarioId, $usuarioNome, 'Logout');
}

// Limpa todas as variáveis de sessão
$_SESSION = array();

// Se a sessão usar cookies, também apaga o cookie da sessão.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destrói a sessão
session_destroy();

// Redireciona para a página de login
header('Location: login.php');
exit;
?>
