<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/Autoload.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['login'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirmar = $_POST['confirmar'] ?? '';

   if ($senha !== $confirmar) {
        $erro = 'As senhas não conferem.';
    } else {
        try {
            $pdo = Conexao::getConnection();

            if (Usuario::buscarPorNome($pdo, $nome)) {
                $erro = 'Já existe um treinador com esse nome.';
            } else {
                $id = Usuario::criarConta($pdo, $nome, $senha);

                // Já entra logado depois de criar a conta
                $_SESSION['logado'] = true;
                $_SESSION['usuario'] = $nome;
                $_SESSION['usuario_id'] = $id;

                header('Location: index.php?rota=menu');
                exit;
            }
        } catch (Throwable $e) {
            $erro = 'Não foi possível criar a conta. Tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PokeArena - Criar conta</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>

    <div class="login-container">
        <h1 class="titulo-jogo">PokeArena</h1>
        <h2>Criar conta</h2>

        <?php if ($erro): ?>
            <p class="erro-msg"><?php echo htmlspecialchars($erro); ?></p>
        <?php endif; ?>

        <form action="cadastro.php" method="post">
            <label for="login">Nome do treinador:</label>
            <input type="text" id="login" name="login" required maxlength="50" value="<?php echo htmlspecialchars($_POST['login'] ?? ''); ?>">

            <label for="senha">Senha:</label>
            <input type="password" id="senha" name="senha" required>

            <label for="confirmar">Confirmar senha:</label>
            <input type="password" id="confirmar" name="confirmar" required>

            <button type="submit">Criar conta</button>
        </form>

        <p class="link-secundario">Já tem conta? <a href="login.php">Entrar</a></p>
    </div>

</body>
</html>
