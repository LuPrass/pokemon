<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>PokeArena - Menu</title>
    <link rel="stylesheet" href="assets/css/menu.css">
</head>
<body>

    <div class="menu-container">
        <h1 class="titulo-jogo">PokeArena</h1>
        <h2>Menu</h2>
        <p>Bem-vindo, <b><?php echo htmlspecialchars($_SESSION['usuario']); ?></b>!</p>

        <?php if (!empty($_SESSION['meu_time'])): ?>
            <a class="menu-opcao" href="index.php?rota=batalha">Batalha</a>
        <?php else: ?>
            <a class="menu-opcao" href="index.php?rota=builder">Construir Time</a>
        <?php endif; ?>
        <a class="menu-opcao" href="index.php?rota=pokemons">Menu de Pokémons</a>
        <a class="menu-opcao" href="index.php?rota=loja">PokéMart</a>
        <?php
        if($_SESSION['usuario_id'] == 1){
            echo '<a class="menu-opcao" href="logs/historico.php">Ver Logs</a>';
        }
        ?>

        <a class="menu-sair" href="logout.php">Sair</a>
    </div>

</body>
</html>
