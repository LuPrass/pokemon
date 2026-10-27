<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['logado']) && $_SESSION['logado'] === true) {
    header('Location: index.php?rota=menu');
    exit;
}

require_once __DIR__ . '/Autoload.php';

$usuario_validos = [
    'adm' => '123456',
];    

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $senha = $_POST['senha'] ?? '';

    $autenticado = false;
    $usuarioId = null;
    $usuarioNome = null;

    // 1. Busca e autentica pelo Banco de Dados (tabela usuario)
    try {
        $pdo = Conexao::getConnection();
        $usuario = Usuario::buscarPorNome($pdo, $login);

        if ($usuario && (password_verify($senha, $usuario['senha']) || $senha === $usuario['senha'])) {
            $autenticado = true;
            $usuarioId = (int)$usuario['id'];
            $usuarioNome = $usuario['nome'];
        }
    } catch (Throwable $e) {
        // Falha de conexão ou tabela
    }

    // 2. Fallback para os usuários válidos em memória (material de apoio)
    if (!$autenticado && isset($usuario_validos[$login]) && $usuario_validos[$login] === $senha) {
        $autenticado = true;
        $usuarioId = 1;
        $usuarioNome = $login;
    }

    // Se autenticado, grava na sessão e redireciona
    if ($autenticado) {
        $_SESSION['logado'] = true;
        $_SESSION['usuario'] = $usuarioNome;
        $_SESSION['usuario_id'] = $usuarioId;
        header('Location: index.php?rota=menu');
        exit;
    } else {
        $erro = 'Login ou senha inválidos.';
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>

    <div class="login-container">
        <h2>Login</h2>
        <?php if ($erro):?>
            <p style="color: red;"><?php echo $erro;?></p>
        <?php endif; ?>
        <form action="login.php" method="post">
            <label for="login">Login:</label>
            <input type="text" id="login" name="login" required value="<?php echo htmlspecialchars($_POST['login'] ?? ''); ?>">

            <label for="senha">Senha:</label>
            <input type="password" id="senha" name="senha" required>

            <button type="submit">Entrar</button>
        </form>

        <p class="link-secundario">Não tem conta? <a href="cadastro.php">Criar conta</a></p>
    </div>

</body>
</html>
