<?php
require_once __DIR__ . '/../../../verifica_login.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Menu</title>
    <link rel="stylesheet" href="assets/css/menu.css">
</head>
<body>

    <div class="menu-container">
        <h1>Menu</h1>
        <p>Bem-vindo, <b><?php echo htmlspecialchars($_SESSION['usuario']); ?></b>!</p>

        <a class="menu-opcao" href="index.php?rota=builder">Batalha</a>
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
