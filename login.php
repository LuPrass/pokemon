<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/Autoload.php';

// Já logado: vai direto para o menu
if (isset($_SESSION['logado']) && $_SESSION['logado'] === true) {
    header('Location: index.php?rota=menu');
    exit;
}

$erro = '';
$semConta = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $senha = $_POST['senha'] ?? '';

    try {
        $usuario = Usuario::buscarPorNome(Conexao::getConnection(), $login);

        if (!$usuario) {
            // Não possui conta: informa que precisa criar uma
            $semConta = true;
            $erro = 'Conta não encontrada. Você precisa criar uma conta para jogar.';
        } elseif (password_verify($senha, $usuario['senha'])) {
            session_regenerate_id(true);
            $_SESSION['logado'] = true;
            $_SESSION['usuario'] = $usuario['nome'];
            $_SESSION['usuario_id'] = (int)$usuario['id'];

            header('Location: index.php?rota=menu');
            exit;
        } else {
            $erro = 'Senha incorreta!';
        }
    } catch (Exception $e) {
        $erro = 'Não foi possível conectar ao banco de dados.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>

    <div class="login-container">
        <h2>Login</h2>

        <?php if ($erro): ?>
            <p class="erro-msg"><?php echo htmlspecialchars($erro); ?></p>
        <?php endif; ?>

        <?php if ($semConta): ?>
            <p class="aviso-conta"><a href="cadastro.php">Clique aqui para criar sua conta</a></p>
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
