<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redireciona para o login se a sessão não estiver ativa
if (empty($_SESSION['logado'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PokéArena - Menu Principal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/menu.css?v=<?= time() ?>">
</head>
<body>

    <div class="menu-viewport">
        <div class="menu-card">
            
            <div class="menu-header">
                <img src="assets/img/pokearena_title.png" alt="PokéArena" class="menu-logo">
                <span class="menu-subtitle">MENU PRINCIPAL</span>
            </div>

            <div class="trainer-badge">
                <span>Treinador:</span>
                <span class="trainer-name"><?php echo htmlspecialchars($_SESSION['usuario'] ?? 'Treinador'); ?></span>
            </div>

            <div class="menu-options">
                <?php if (!empty($_SESSION['meu_time'])): ?>
                    <a class="menu-btn btn-batalha" href="index.php?rota=batalha">Batalha</a>
                <?php else: ?>
                    <a class="menu-btn btn-builder" href="index.php?rota=builder">Construir Time</a>
                <?php endif; ?>

                <a class="menu-btn btn-pokemons" href="index.php?rota=pokemons">Menu de Pokémons</a>
                <a class="menu-btn btn-loja" href="index.php?rota=loja">PokéMart</a>

                <?php if (!empty($_SESSION['usuario_id']) && (int)$_SESSION['usuario_id'] === 1): ?>
                    <a class="menu-btn btn-logs" href="index.php?rota=logs">Ver Logs</a>
                <?php endif; ?>

                <a class="btn-sair" href="logout.php">Sair do Jogo</a>
            </div>

        </div>
    </div>

</body>
</html>
