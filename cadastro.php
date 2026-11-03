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
    $nome = trim($_POST['login'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirmar = $_POST['confirmar'] ?? '';

    if ($senha !== $confirmar) {
        $erro = 'As senhas não conferem.';
    } elseif (strlen($nome) < 3) {
        $erro = 'O nome de treinador deve ter pelo menos 3 caracteres.';
    } elseif (strlen($senha) < 4) {
        $erro = 'A senha deve ter pelo menos 4 caracteres.';
    } else {
        try {
            $pdo = Conexao::getConnection();

            if (Usuario::buscarPorNome($pdo, $nome)) {
                $erro = 'Já existe um treinador com esse nome.';
            } else {
                $id = Usuario::criarConta($pdo, $nome, $senha);

                // Já entra autenticado após o cadastro
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
    <title>PokéArena - Novo Treinador</title>
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
                <span class="game-subtitle">CADASTRO DE NOVO TREINADOR</span>
            </div>

            <?php if ($erro): ?>
                <div class="login-alert"><?php echo htmlspecialchars($erro); ?></div>
            <?php endif; ?>

            <form action="cadastro.php" method="post" class="login-form">
                
                <div class="input-group input-username">
                    <input type="text" id="login" name="login" required maxlength="50"
                           value="<?php echo htmlspecialchars($_POST['login'] ?? ''); ?>" 
                           placeholder="Nome do treinador" autocomplete="username" autofocus>
                </div>

                <div class="input-group input-password">
                    <input type="password" id="senha" name="senha" required 
                           placeholder="Senha" autocomplete="new-password">
                    <button type="button" class="btn-eye" id="toggleSenha" aria-label="Mostrar ou ocultar senha"></button>
                </div>

                <div class="input-group input-password">
                    <input type="password" id="confirmar" name="confirmar" required 
                           placeholder="Confirmar senha" autocomplete="new-password">
                    <button type="button" class="btn-eye" id="toggleConfirmar" aria-label="Mostrar ou ocultar confirmação de senha"></button>
                </div>

                <button type="submit" class="btn-cadastrar">CRIAR CONTA</button>
                <a href="login.php" class="btn-entrar">JÁ TENHO UMA CONTA</a>

            </form>

        </div>
    </div>

    <script>
        function setupTogglePassword(btnId, inputId) {
            const btn = document.getElementById(btnId);
            const input = document.getElementById(inputId);
            if (btn && input) {
                btn.addEventListener('click', function() {
                    const isPassword = input.getAttribute('type') === 'password';
                    input.setAttribute('type', isPassword ? 'text' : 'password');
                    btn.classList.toggle('visible', isPassword);
                });
            }
        }

        setupTogglePassword('toggleSenha', 'senha');
        setupTogglePassword('toggleConfirmar', 'confirmar');
    </script>
</body>
</html>
