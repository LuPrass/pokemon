<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['logado']) && $_SESSION['logado'] === true) {
    header('Location: index.php?rota=menu');
    exit;
}

require_once __DIR__ . '/Autoload.php'; 

$erro = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $senha = $_POST['senha'] ?? '';

    $autenticado = false;
    $usuarioId = null;
    $usuarioNome = null;

    try {
        $pdo = Conexao::getConnection();
        $usuario = Usuario::buscarPorNome($pdo, $login);

        // Verifica a senha (suporta hash seguro ou texto plano legado)
        if ($usuario && (password_verify($senha, $usuario['senha']) || $senha === $usuario['senha'])) {
            $autenticado = true;
            $usuarioId = (int)$usuario['id'];
            $usuarioNome = $usuario['nome'];
        }
    } catch (Throwable $e) {
        $erro = 'Não foi possível acessar o banco de dados para autenticar.';
    }

    if ($autenticado) {
        $_SESSION['logado'] = true;
        $_SESSION['usuario'] = $usuarioNome;
        $_SESSION['usuario_id'] = $usuarioId;
        header('Location: index.php?rota=menu');
        exit;
    } elseif (!$erro) {
        $erro = 'Login ou senha inválidos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PokéArena - Conexão de Treinador</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/login.css?v=<?= time() ?>">
</head>
<body>

    <div class="game-viewport">
        <div class="login-card">
            
            <div class="card-header">
                <img src="assets/img/pokearena_title.png" alt="PokéArena" class="pokearena-logo">
                <span class="game-subtitle">THE INFINITE POKÉMON ADVENTURE</span>
            </div>

            <?php if ($erro): ?>
                <div class="login-alert"><?php echo htmlspecialchars($erro); ?></div>
            <?php endif; ?>

            <form action="login.php" method="post" class="login-form">
                
                <div class="input-group input-username">
                    <input type="text" id="login" name="login" required 
                           value="<?php echo htmlspecialchars($_POST['login'] ?? ''); ?>" 
                           placeholder="Nome de usuário" autocomplete="username" autofocus>
                </div>

                <div class="input-group input-password">
                    <input type="password" id="senha" name="senha" required 
                           placeholder="Senha" autocomplete="current-password">
                    <button type="button" class="btn-eye" id="togglePassword" aria-label="Mostrar ou ocultar senha"></button>
                </div>

                <button type="submit" class="btn-entrar">ENTRAR</button>
                <a href="cadastro.php" class="btn-cadastrar">CADASTRAR NOVA CONTA</a>
                <a href="javascript:void(0)" class="btn-esqueci" id="btnForgot">ESQUECI A SENHA?</a>

            </form>

        </div>
    </div>

    <script>
        const togglePassword = document.getElementById('togglePassword');
        const senhaInput = document.getElementById('senha');

        if (togglePassword && senhaInput) {
            togglePassword.addEventListener('click', function() {
                const isPassword = senhaInput.getAttribute('type') === 'password';
                senhaInput.setAttribute('type', isPassword ? 'text' : 'password');
                togglePassword.classList.toggle('visible', isPassword);
            });
        }

        const btnForgot = document.getElementById('btnForgot');
        if (btnForgot) {
            btnForgot.addEventListener('click', function(e) {
                e.preventDefault();
                alert('Para recuperar o acesso à sua conta, solicite ao administrador ou crie uma nova conta de treinador!');
            });
        }
    </script>
</body>
</html>
